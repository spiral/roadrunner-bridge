<?php

declare(strict_types=1);

namespace Spiral\Tests;

use Spiral\Files\Files;

abstract class ConsoleTestCase extends TestCase
{
    public function generateGRPCService(): string
    {
        $appPath = $this->getDirectoryByAlias('app');

        $result = $this->runCommand('grpc:generate', [
            'path' => $appPath,
            'namespace' => 'Spiral\\App',
        ]);

        $files = [
            'GRPC/EchoService/EchoInterface.php',
            'GRPC/EchoService/Message.php',
            'GRPC/EchoService/GPBMetadata/PBEcho.php',
        ];

        foreach ($files as $file) {
            require_once $appPath . $file;
        }

        return $result;
    }

    public function deleteGRPCService(): void
    {
        $fs = new Files();
        $appPath = $this->getDirectoryByAlias('app');

        if ($fs->isDirectory($appPath . 'GRPC/EchoService')) {
            $fs->deleteDirectory($appPath . 'GRPC/EchoService');
        }
        if ($fs->isDirectory($appPath . 'Bootloader')) {
            $fs->deleteDirectory($appPath . 'Bootloader');
        }
        if ($fs->isFile($appPath . 'Config/GRPCServicesConfig.php')) {
            $fs->delete($appPath . 'Config/GRPCServicesConfig.php');
        }
    }
}
