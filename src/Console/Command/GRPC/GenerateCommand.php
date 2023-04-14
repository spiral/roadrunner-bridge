<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\GRPC;

use Codedungeon\PHPCliColors\Color;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\KernelInterface;
use Spiral\Console\Command;
use Spiral\Files\FilesInterface;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\CommandExecutor;
use Spiral\RoadRunnerBridge\GRPC\Exception\CompileException;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistryInterface;
use Spiral\RoadRunnerBridge\GRPC\ProtocCommandBuilder;
use Spiral\RoadRunnerBridge\GRPC\ProtoCompiler;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;

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
        GeneratorRegistryInterface $generatorRegistry
    ): int {
        $binaryPath = $config->getBinaryPath();

        if ($binaryPath !== null && !\file_exists($binaryPath)) {
            $this->error("Protoc plugin binary `$binaryPath` was not found. Use command `./vendor/bin/rr download-protoc-binary` to download it.");

            return self::FAILURE;
        }

        \assert($binaryPath !== null);

        $compiler = new ProtoCompiler(
            $this->getPath($kernel, $config->getGeneratedPath()),
            $this->getNamespace($kernel, $config->getNamespace()),
            $files,
            new ProtocCommandBuilder($files, $config, $binaryPath),
            new CommandExecutor()
        );

        $compiled = [];
        foreach ($repository->getProtos() as $protoFile) {
            if (!\file_exists($protoFile)) {
                $this->sprintf('<error>Proto file `%s` not found.</error>', $protoFile);
                continue;
            }

            $this->sprintf("<info>Compiling <fg=cyan>`%s`</fg=cyan>:</info>\n", $protoFile);

            try {
                $result = $compiler->compile($protoFile);
            } catch (CompileException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $this->sprintf("<error>Error:</error> <fg=red>%s</fg=red>\n", $e->getMessage());
                continue;
            }

            if ($result === []) {
                $this->sprintf("<info>No files were generated for `%s`.</info>\n", $protoFile);
                continue;
            }

            foreach ($result as $file) {
                $this->sprintf(
                    "<fg=green>•</fg=green> %s%s%s\n",
                    Color::LIGHT_WHITE,
                    $files->relativePath($file, $dirs->get('root')),
                    Color::RESET
                );

                $compiled[] = $file;
            }
        }

        foreach ($generatorRegistry->getGenerators() as $generator) {
            /** @psalm-suppress ArgumentTypeCoercion */
            $generator->run(
                $compiled,
                $this->getPath($kernel, $config->getGeneratedPath()),
                $this->getNamespace($kernel, $config->getNamespace())
            );
        }

        return self::SUCCESS;
    }

    /**
     * Get or detect base source code path. By default fallbacks to kernel location.
     * @param non-empty-string|null $generatedPath
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

        /** @psalm-suppress LessSpecificReturnStatement */
        return \dirname($r->getFileName());
    }

    /**
     * Get or detect base namespace. By default fallbacks to kernel namespace.
     * @return non-empty-string
     * @psalm-suppress LessSpecificReturnStatement
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
}
