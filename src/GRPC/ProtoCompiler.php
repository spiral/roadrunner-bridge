<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Spiral\Files\FilesInterface;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Exception\CompileException;

/**
 * Compiles GRPC protobuf declaration and moves files into proper location.
 */
final class ProtoCompiler
{
    private readonly string $baseNamespace;

    public function __construct(
        private readonly string $basePath,
        string $baseNamespace,
        private readonly FilesInterface $files,
        private readonly ?string $protocBinaryPath = null,
        private readonly ?string $servicesBasePath = null,
    ) {
        $this->baseNamespace = \str_replace('\\', '/', \rtrim($baseNamespace, '\\'));
    }

    /**
     * @throws CompileException
     */
    public function compile(string $protoFile): array
    {
        $tmpDir = $this->tmpDir();

        \exec(
            \sprintf(
                'protoc %s --php_out=%s --php-grpc_out=%s -I=%s -I=%s %s 2>&1',
                $this->protocBinaryPath ? '--plugin=' . $this->protocBinaryPath : '',
                \escapeshellarg($tmpDir),
                \escapeshellarg($tmpDir),
                \escapeshellarg(realpath($this->basePath . $this->servicesBasePath)),
                \escapeshellarg(dirname($protoFile)),
                \implode(' ', \array_map('escapeshellarg', $this->getProtoFiles($protoFile)))
            ),
            $output
        );

        $output = \trim(\implode("\n", $output), "\n ,");

        if ($output !== '') {
            $this->files->deleteDirectory($tmpDir);
            throw new CompileException($output);
        }

        // copying files (using relative path and namespace)
        $result = [];
        foreach ($this->files->getFiles($tmpDir) as $file) {
            $result[] = $this->copy($tmpDir, $file);
        }

        $this->files->deleteDirectory($tmpDir);

        return $result;
    }

    private function copy(string $tmpDir, string $file): string
    {
        $source = \ltrim($this->files->relativePath($file, $tmpDir), '\\/');
        if (\str_starts_with($source, $this->baseNamespace)) {
            $source = \ltrim(\substr($source, \strlen($this->baseNamespace)), '\\/');
        }

        $target = $this->files->normalizePath($this->basePath . '/' . $source);

        $this->files->ensureDirectory(\dirname($target));
        $this->files->copy($file, $target);

        return $target;
    }

    private function tmpDir(): string
    {
        $directory = \sys_get_temp_dir() . '/' . \spl_object_hash($this);
        $this->files->ensureDirectory($directory);

        return $this->files->normalizePath($directory, true);
    }

    /**
     * Include all proto files from the directory.
     */
    private function getProtoFiles(string $protoFile): array
    {
        return \array_filter(
            $this->files->getFiles(\dirname($protoFile)),
            static fn (string $file) => str_contains($file, '.proto')
        );
    }
}
