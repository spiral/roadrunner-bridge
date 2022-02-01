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
            require_once $this->app->dir('app').$file;
        }

        return $result;
    }

    public function deleteGRPCService(): void
    {
        $fs = new Files();
        if ($fs->isDirectory($this->app->dir('app').'GRPC/EchoService')) {
            $fs->deleteDirectory($this->app->dir('app').'GRPC/EchoService');
        }
    }

    public function runCommand(
        string $command,
        array $args = [],
        OutputInterface $output = null,
        ?int $verbosityLevel = null
    ): string {
        $input = new ArrayInput($args);
        $output = $output ?? new BufferedOutput();
        if ($verbosityLevel !== null) {
            $output->setVerbosity($verbosityLevel);
        }

        $this->app->console()->run($command, $input, $output);

        return $output->fetch();
    }

    public function runCommandDebug(string $command, array $args = [], OutputInterface $output = null): string
    {
        return $this->runCommand(
            $command,
            $args,
            $output,
            BufferedOutput::VERBOSITY_VERBOSE
        );
    }

    public function runCommandVeryVerbose(string $command, array $args = [], OutputInterface $output = null): string
    {
        return $this->runCommand(
            $command,
            $args,
            $output,
            BufferedOutput::VERBOSITY_DEBUG
        );
    }
}
