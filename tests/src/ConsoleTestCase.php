<?php

declare(strict_types=1);

namespace Spiral\Tests;

use Spiral\Files\Files;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ConsoleTestCase extends TestCase
{
    public function generateGRPCService(): string
    {
        $result = $this->runCommand('grpc:generate');

        $files = [
            'GRPC/EchoService/EchoInterface.php',
            'GRPC/EchoService/Message.php',
            'GRPC/EchoService/GPBMetadata/PBEcho.php',
        ];

        foreach ($files as $file) {
            require_once $this->getDirectoryByAlias('app') . $file;
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
