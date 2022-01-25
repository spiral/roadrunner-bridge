<?php

declare(strict_types=1);

namespace Spiral\Tests;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ConsoleTestCase extends TestCase
{
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
