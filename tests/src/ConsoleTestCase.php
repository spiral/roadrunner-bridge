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
            require $appPath . $file;
        }

        return $result;
    }

    public function deleteGRPCService(): void
    {
        $fs = new Files();
        if ($fs->isDirectory($this->getDirectoryByAlias('app') . 'GRPC/EchoService')) {
            $fs->deleteDirectory($this->getDirectoryByAlias('app') . 'GRPC/EchoService');
        }
    }
}
