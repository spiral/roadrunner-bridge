<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Command;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Symfony\Component\Console\Input\InputArgument;

final class PauseCommand extends Command
{
    protected const NAME = 'queue:pause';
    protected const DESCRIPTION = 'Pause consuming jobs for queue with given name';
    protected const ARGUMENTS = [
        ['queue', InputArgument::REQUIRED, 'Queue name'],
    ];

    public function perform(JobsInterface $jobs): void
    {
        $name = $this->argument('queue');

        if ($this->isVerbose()) {
            $this->writeln(sprintf('<info>Pausing queue [%s]</info>', $name));
        }

        $jobs->pause($name);

        $this->writeln(sprintf('<info>Queue [%s] has been paused.</info>', $name));
    }
}
