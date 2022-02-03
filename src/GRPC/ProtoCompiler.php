<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Spiral\Files\FilesInterface;
use Spiral\RoadRunnerBridge\GRPC\Exception\CompileException;

/**
 * Compiles GRPC protobuf declaration and moves files into proper location.
 */
final class ProtoCompiler
{
    private FilesInterface $files;
    private string $basePath;
    private string $baseNamespace;
    private ?string $protocBinaryPath = null;

    public function __construct(
        string $basePath,
        string $baseNamespace,
        FilesInterface $files,
        ?string $protocBinaryPath = null
    ) {
        $this->basePath = $basePath;
        $this->baseNamespace = str_replace('\\', '/', rtrim($baseNamespace, '\\'));
        $this->files = $files;
        $this->protocBinaryPath = $protocBinaryPath;
    }

    /**
     * @throws CompileException
     */
    public function compile(string $protoFile): array
    {
        $tmpDir = $this->tmpDir();

        exec(
            sprintf(
                'protoc %s --php_out=%s --php-grpc_out=%s -I %s %s 2>&1',
                $this->protocBinaryPath ? '--plugin=' . $this->protocBinaryPath : '',
                escapeshellarg($tmpDir),
                escapeshellarg($tmpDir),
                escapeshellarg(dirname($protoFile)),
                implode(' ', array_map('escapeshellarg', $this->getProtoFiles($protoFile)))
            ),
            $output
        );

        $output = trim(implode("\n", $output), "\n ,");

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

    /**
     * @param string $tmpDir
     * @param string $file
     *
     * @return string
     */
    private function copy(string $tmpDir, string $file): string
    {
        $source = ltrim($this->files->relativePath($file, $tmpDir), '\\/');
        if (strpos($source, $this->baseNamespace) === 0) {
            $source = ltrim(substr($source, strlen($this->baseNamespace)), '\\/');
        }

        $target = $this->files->normalizePath($this->basePath . '/' . $source);

        $this->files->ensureDirectory(dirname($target));
        var_dump($target);
        $this->files->copy($file, $target);

        return $target;
    }

    /**
     * @return string
     */
    private function tmpDir(): string
    {
        $directory = sys_get_temp_dir() . '/' . spl_object_hash($this);
        $this->files->ensureDirectory($directory);

        return $this->files->normalizePath($directory, true);
    }

    /**
     * Include all proto files from the directory.
     *
     * @param string $protoFile
     *
     * @return array
     */
    private function getProtoFiles(string $protoFile): array
    {
        return array_filter(
            $this->files->getFiles(dirname($protoFile)),
            function ($file) {
                return strpos($file, '.proto') !== false;
            }
        );
    }
}
