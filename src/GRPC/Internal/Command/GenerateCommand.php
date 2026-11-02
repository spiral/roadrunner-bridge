<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal\Command;

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
use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\Path;
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
        $root = $dirs->get('root');
        $locator = new BinaryLocator($root);
        $userTemplate = Path::from($root, $config->getBufTemplate());

        try {
            $bufPath = $locator->locate('buf', 'bufPath', Path::from($root, $config->getBufPath()));
            $template = $userTemplate === null
                ? BufTemplate::local(
                    $locator->locate('protoc', 'protocPath', Path::from($root, $config->getProtocPath())),
                    $locator->locate('protoc-gen-php-grpc', 'pluginPath', Path::from($root, $config->getPluginPath())),
                )
                : BufTemplate::user($userTemplate);
        } catch (BinaryNotFoundException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($userTemplate !== null && !\is_file($userTemplate)) {
            $this->error("The buf template `$userTemplate` set in `bufTemplate` was not found.");

            return self::FAILURE;
        }

        $basePath = Path::from($root, $config->getServicesBasePath());
        if ($basePath !== null) {
            $realBasePath = \realpath($basePath);
            if ($realBasePath === false || !\is_dir($realBasePath)) {
                $this->error("The services base path `$basePath` set in `servicesBasePath` was not found.");

                return self::FAILURE;
            }

            $basePath = $realBasePath;
        }

        $generatedPath = $this->getPath($kernel, Path::from($root, $config->getGeneratedPath()));
        $namespace = $this->getNamespace($kernel, $config->getNamespace());

        $compiler = new ProtoCompiler(
            $generatedPath,
            $namespace,
            $files,
            new BufCommandBuilder($bufPath),
            new ProcessRunner(),
            $template,
            $root,
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
                $this->sprintf(
                    "<fg=green>•</fg=green> <fg=white;options=bold>%s</>\n",
                    $files->relativePath($file, $root),
                );

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
                    $files->relativePath($generatedPath . '/' . $topNamespace, $root),
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
     * @param non-empty-string|null $generatedPath
     *
     * @return non-empty-string
     */
    protected function getPath(KernelInterface $kernel, ?string $generatedPath): string
    {
        $path = $this->argument('path');
        if ($path !== 'auto') {
            return $path;
        }

        if ($generatedPath !== null) {
            return $generatedPath;
        }

        $r = new \ReflectionObject($kernel);

        /** @psalm-suppress LessSpecificReturnStatement dirname() never returns an empty string */
        return \dirname($r->getFileName());
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
     * Groups the existing proto files by the buf input they are compiled from: the services base path,
     * or the file's own directory when no base path is set.
     *
     * @param string $root Application root that relative proto file paths resolve from.
     * @param string|null $basePath Real path of the services base path.
     *
     * @return array<string, list<string>> Input directory => real paths of its proto files.
     */
    private function groupByInput(ProtoFilesRepositoryInterface $repository, string $root, ?string $basePath): array
    {
        $groups = [];
        foreach ($repository->getProtos() as $entry) {
            $protoFile = Path::from($root, $entry);
            if ($protoFile === null || !\file_exists($protoFile)) {
                $this->sprintf("<error>Proto file `%s` not found.</error>\n", $entry);
                $this->failed = true;
                continue;
            }

            $realPath = \realpath($protoFile);
            $protoFile = $realPath === false ? $protoFile : $realPath;

            if ($basePath !== null && !\str_starts_with($protoFile, \rtrim($basePath, '\\/') . \DIRECTORY_SEPARATOR)) {
                $this->sprintf(
                    "<error>Proto file `%s` is outside of the services base path `%s`.</error>\n",
                    $protoFile,
                    $basePath,
                );
                $this->failed = true;
                continue;
            }

            $groups[$basePath ?? \dirname($protoFile)][] = $protoFile;
        }

        return $groups;
    }
}
