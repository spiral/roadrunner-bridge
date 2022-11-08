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

        var_dump($result);

        $files = [
            'GRPC/EchoService/EchoInterface.php',
            'GRPC/EchoService/Message.php',
            'GRPC/EchoService/GPBMetadata/PBEcho.php',
        ];

        $tries = 5;

        foreach ($files as $file) {
            $filePath = $appPath . $file;
            while (!\file_exists($filePath) && $tries > 0) {
                --$tries;
                \usleep(500 * 1000);
            }

            require_once $filePath;
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
