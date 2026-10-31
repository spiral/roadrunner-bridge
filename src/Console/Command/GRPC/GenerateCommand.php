<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\GRPC;

use Codedungeon\PHPCliColors\Color;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\KernelInterface;
use Spiral\Console\Command;
use Spiral\Files\FilesInterface;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Exception\BinaryNotFoundException;
use Spiral\RoadRunnerBridge\GRPC\Exception\CompileException;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistryInterface;
use Spiral\RoadRunnerBridge\GRPC\Internal\BinaryLocator;
use Spiral\RoadRunnerBridge\GRPC\Internal\BufCommandBuilder;
use Spiral\RoadRunnerBridge\GRPC\Internal\BufTemplate;
use Spiral\RoadRunnerBridge\GRPC\Internal\ProcessRunner;
use Spiral\RoadRunnerBridge\GRPC\Internal\ProtoCompiler;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;

/**
 * @internal
 */
final class GenerateCommand extends Command
{
    protected const SIGNATURE = 'grpc:generate
                                         {path=auto : Base path for generated service code}
                                         {namespace=auto : Base namespace for generated service code}';
    protected const DESCRIPTION = 'Generate GPRC service code using protobuf specification';

    public function perform(
        KernelInterface $kernel,
        FilesInterface $files,
        DirectoriesInterface $dirs,
        GRPCConfig $config,
        ProtoFilesRepositoryInterface $repository,
        GeneratorRegistryInterface $generatorRegistry,
    ): int {
        $locator = new BinaryLocator($dirs->get('root'));
        $userTemplate = $config->getBufTemplate();

        try {
            $bufPath = $locator->locate('buf', 'bufPath', $config->getBufPath());
            $template = $userTemplate === null
                ? BufTemplate::local(
                    $locator->locate('protoc', 'protocPath', $config->getProtocPath()),
                    $locator->locate('protoc-gen-php-grpc', 'pluginPath', $config->getPluginPath()),
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

        $basePath = $config->getServicesBasePath();
        if ($basePath !== null) {
            $realBasePath = \realpath($basePath);
            if ($realBasePath === false || !\is_dir($realBasePath)) {
                $this->error("The services base path `$basePath` set in `servicesBasePath` was not found.");

                return self::FAILURE;
            }

            $basePath = $realBasePath;
        }

        $generatedPath = $this->getPath($kernel, $config->getGeneratedPath());
        $namespace = $this->getNamespace($kernel, $config->getNamespace());

        $compiler = new ProtoCompiler(
            $generatedPath,
            $namespace,
            $files,
            new BufCommandBuilder($bufPath),
            new ProcessRunner(),
            $template,
        );

        $compiled = [];
        foreach ($this->groupByInput($repository, $basePath) as $input => $protoFiles) {
            foreach ($protoFiles as $protoFile) {
                $this->sprintf("<info>Compiling <fg=cyan>`%s`</fg=cyan>:</info>\n", $protoFile);
            }

            try {
                $result = $compiler->compile($input, $protoFiles);
            } catch (CompileException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->sprintf("<error>Error:</error> <fg=red>%s</fg=red>\n", $e->getMessage());
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
                    "<fg=green>•</fg=green> %s%s%s\n",
                    Color::LIGHT_WHITE,
                    $files->relativePath($file, $dirs->get('root')),
                    Color::RESET,
                );

                $compiled[] = $file;
            }
        }

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
     * @param string|null $basePath Real path of the services base path.
     *
     * @return array<string, list<string>> Input directory => real paths of its proto files.
     */
    private function groupByInput(ProtoFilesRepositoryInterface $repository, ?string $basePath): array
    {
        $groups = [];
        foreach ($repository->getProtos() as $protoFile) {
            if (!\file_exists($protoFile)) {
                $this->sprintf('<error>Proto file `%s` not found.</error>', $protoFile);
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
                continue;
            }

            $groups[$basePath ?? \dirname($protoFile)][] = $protoFile;
        }

        return $groups;
    }
}
