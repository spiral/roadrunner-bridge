<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\GRPC;

use Codedungeon\PHPCliColors\Color;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\KernelInterface;
use Spiral\Console\Command;
use Spiral\Files\FilesInterface;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Exception\CompileException;
use Spiral\RoadRunnerBridge\GRPC\ProtoCompiler;

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
        GRPCConfig $config
    ): int {
        $binaryPath = $config->getBinaryPath();

        if ($binaryPath !== null && !\file_exists($binaryPath)) {
            $this->sprintf('<error>PHP Server plugin binary `%s` not found.</error>', $binaryPath);

            return self::FAILURE;
        }

        $compiler = new ProtoCompiler(
            $this->getPath($kernel),
            $this->getNamespace($kernel),
            $files,
            $binaryPath
        );

        foreach ($config->getServices() as $protoFile) {
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
            }
        }

        return self::SUCCESS;
    }

    /**
     * Get or detect base source code path. By default fallbacks to kernel location.
     */
    protected function getPath(KernelInterface $kernel): string
    {
        $path = $this->argument('path');
        if ($path !== 'auto') {
            return $path;
        }

        $r = new \ReflectionObject($kernel);

        return \dirname($r->getFileName());
    }

    /**
     * Get or detect base namespace. By default fallbacks to kernel namespace.
     */
    protected function getNamespace(KernelInterface $kernel): string
    {
        $namespace = $this->argument('namespace');
        if ($namespace !== 'auto') {
            return $namespace;
        }

        return (new \ReflectionObject($kernel))->getNamespaceName();
    }
}
