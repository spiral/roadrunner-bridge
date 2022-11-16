<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Spiral\Files\FilesInterface;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;

/**
 * @internal
 */
final class ProtocCommandBuilder
{
    public function __construct(
        private readonly FilesInterface $files,
        private readonly GRPCConfig $config,
        private readonly string $protocBinaryPath
    ) {
    }

    public function build(string $protoFile, string $tmpDir): string
    {
        return \sprintf(
            'protoc %s --php_out=%s --php-grpc_out=%s -I=%s -I=%s %s 2>&1',
            $this->protocBinaryPath ? '--plugin=' . $this->protocBinaryPath : '',
            \escapeshellarg($tmpDir),
            \escapeshellarg($tmpDir),
            \escapeshellarg($this->config->getServicesBasePath()),
            \escapeshellarg(dirname($protoFile)),
            \implode(' ', \array_map('escapeshellarg', $this->getProtoFiles($protoFile)))
        );
    }

    /**
     * Include all proto files from the directory.
     */
    private function getProtoFiles(string $protoFile): array
    {
        return \array_filter(
            $this->files->getFiles(\dirname($protoFile)),
            static fn(string $file) => \str_ends_with($file, '.proto')
        );
    }
}
