<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal\Command;

use Internal\Path;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\KernelInterface;
use Spiral\Console\Command;
use Spiral\Files\FilesInterface;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Exception\BinaryNotFoundException;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistryInterface;
use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\BinaryLocator;
use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\BufCommandBuilder;
use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\BufTemplate;
use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\ProcessRunner;
use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\ProtoCompiler;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;

/**
 * @internal
 */
final class GenerateCommand extends Command
{
    protected const SIGNATURE = 'grpc:generate
                                         {path=auto : Base path for generated service code}
                                         {namespace=auto : Base namespace for generated service code}';
    protected const DESCRIPTION = 'Generate gRPC service code using protobuf specification';

    /** Set when a proto file is skipped or fails to compile; the command then exits with a failure. */
    private bool $failed = false;

    public function perform(
        KernelInterface $kernel,
        FilesInterface $files,
        DirectoriesInterface $dirs,
        GRPCConfig $config,
        ProtoFilesRepositoryInterface $repository,
        GeneratorRegistryInterface $generatorRegistry,
    ): int {
        // buf runs in the root, so relative config paths resolve from it here as well.
        $root = Path::create($dirs->get('root'))->absolute();
        $locator = new BinaryLocator((string) $root);
        $userTemplate = self::fromRoot($root, $config->getBufTemplate());

        try {
            $bufPath = $locator->locate('buf', 'bufPath', self::fromRoot($root, $config->getBufPath()));
            $template = $userTemplate === null
                ? BufTemplate::local(
                    $locator->locate('protoc', 'protocPath', self::fromRoot($root, $config->getProtocPath())),
                    $locator->locate('protoc-gen-php-grpc', 'pluginPath', self::fromRoot($root, $config->getPluginPath())),
                )
                : BufTemplate::user((string) $userTemplate);
        } catch (BinaryNotFoundException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($userTemplate !== null && !$userTemplate->isFile()) {
            $this->error("The buf template `$userTemplate` set in `bufTemplate` was not found.");

            return self::FAILURE;
        }

        $basePath = self::fromRoot($root, $config->getServicesBasePath());
        if ($basePath !== null && !$basePath->isDir()) {
            $this->error("The services base path `$basePath` set in `servicesBasePath` was not found.");

            return self::FAILURE;
        }

        $generatedPath = $this->getPath($kernel, self::fromRoot($root, $config->getGeneratedPath()));
        $namespace = $this->getNamespace($kernel, $config->getNamespace());

        $compiler = new ProtoCompiler(
            $generatedPath,
            $namespace,
            $files,
            new BufCommandBuilder($bufPath),
            new ProcessRunner(),
            $template,
            (string) $root,
        );

        $this->failed = false;
        $compiled = [];
        $foreign = [];
        foreach ($this->groupByInput($repository, $root, $basePath) as $input => $protoFiles) {
            foreach ($protoFiles as $protoFile) {
                $this->sprintf("<info>Compiling <fg=cyan>`%s`</fg=cyan>:</info>\n", $protoFile);
            }

            try {
                $result = $compiler->compile($input, $protoFiles);
            } catch (\Throwable $e) {
                $this->sprintf("<error>Error:</error> <fg=red>%s</fg=red>\n", $e->getMessage());
                $this->failed = true;
                continue;
            }

            if ($result->warnings !== '') {
                $this->sprintf("<comment>%s</comment>\n", $result->warnings);
            }

            if ($result->files === []) {
                $this->sprintf("<info>No files were generated for `%s`.</info>\n", \implode('`, `', $protoFiles));
                continue;
            }

            foreach ($result->files as $file) {
                $this->sprintf("<fg=green>•</fg=green> <fg=white;options=bold>%s</>\n", self::shown($file, $root));

                $compiled[] = $file;
            }

            $foreign = [...$foreign, ...$result->foreignNamespaces];
        }

        if ($foreign !== []) {
            $this->sprintf(
                "<comment>Classes outside the base namespace `%s` keep their full namespace as the path and need their own autoload rules:</comment>\n",
                $namespace,
            );
            foreach (\array_unique($foreign) as $topNamespace) {
                $this->sprintf(
                    '  "%s\\\\": "%s/"' . "\n",
                    $topNamespace,
                    self::shown(Path::create($generatedPath)->join($topNamespace), $root),
                );
            }
        }

        if ($this->failed) {
            // A generator may write an aggregate of all services and would drop the ones that failed.
            $this->writeln('<comment>Generators were skipped because of the errors above.</comment>');

            return self::FAILURE;
        }

        // Groups that import the same file generate it each.
        $compiled = \array_values(\array_unique($compiled));
        foreach ($generatorRegistry->getGenerators() as $generator) {
            /** @psalm-suppress ArgumentTypeCoercion Paths of generated files are never empty */
            $generator->run($compiled, $generatedPath, $namespace);
        }

        return self::SUCCESS;
    }

    /**
     * Get or detect base source code path. By default fallbacks to kernel location.
     *
     * The `path` argument is relative to the current directory, as any path typed in a shell.
     *
     * @return non-empty-string
     */
    protected function getPath(KernelInterface $kernel, ?Path $generatedPath): string
    {
        $path = $this->argument('path');
        if ($path !== 'auto') {
            return (string) Path::create($path)->absolute();
        }

        if ($generatedPath !== null) {
            return (string) $generatedPath;
        }

        $r = new \ReflectionObject($kernel);

        return (string) Path::create((string) $r->getFileName())->parent();
    }

    /**
     * Get or detect base namespace. By default fallbacks to kernel namespace.
     *
     * @return non-empty-string
     *
     * @psalm-suppress LessSpecificReturnStatement The argument, the config and the kernel namespace are not expected to be empty
     */
    protected function getNamespace(KernelInterface $kernel, ?string $protoNamespace): string
    {
        $namespace = $this->argument('namespace');
        if ($namespace !== 'auto') {
            return $namespace;
        }

        if ($protoNamespace !== null) {
            return $protoNamespace;
        }

        return (new \ReflectionObject($kernel))->getNamespaceName();
    }

    /**
     * A config path: a relative one resolves from the root. An empty value means unset, as one read from an unset
     * environment variable.
     */
    private static function fromRoot(Path $root, ?string $path): ?Path
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = Path::create($path);

        return $path->isAbsolute() ? $path : $root->join($path);
    }

    /**
     * The path as printed: relative to the root when it lies under it.
     */
    private static function shown(Path|string $path, Path $root): string
    {
        $path = Path::create($path);

        return (string) ($path->isWithin($root) ? $path->tryRelative($root) : $path);
    }

    /**
     * Groups the existing proto files by the buf input they are compiled from: the services base path,
     * or the file's own directory when no base path is set.
     *
     * @param Path $root Application root that relative proto file paths resolve from.
     * @param Path|null $basePath The services base path.
     *
     * @return array<string, list<string>> Input directory => paths of its proto files.
     */
    private function groupByInput(ProtoFilesRepositoryInterface $repository, Path $root, ?Path $basePath): array
    {
        $groups = [];
        foreach ($repository->getProtos() as $entry) {
            $protoFile = self::fromRoot($root, $entry);
            if ($protoFile === null || !$protoFile->isFile()) {
                $this->sprintf("<error>Proto file `%s` not found.</error>\n", $entry);
                $this->failed = true;
                continue;
            }

            if ($basePath !== null && !$protoFile->isWithin($basePath)) {
                $this->sprintf(
                    "<error>Proto file `%s` is outside of the services base path `%s`.</error>\n",
                    $protoFile,
                    $basePath,
                );
                $this->failed = true;
                continue;
            }

            $groups[(string) ($basePath ?? $protoFile->parent())][] = (string) $protoFile;
        }

        return $groups;
    }
}
