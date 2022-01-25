<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Command;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Symfony\Component\Console\Input\InputArgument;

final class ResumeCommand extends Command
{
    protected const NAME = 'queue:resume';
    protected const DESCRIPTION = 'Resume consuming jobs for queue with given name';
    protected const ARGUMENTS = [
        ['queue', InputArgument::REQUIRED, 'Queue name'],
    ];

    public function perform(JobsInterface $jobs): void
    {
        $name = $this->argument('queue');

        if ($this->isVerbose()) {
            $this->writeln(sprintf('<info>Pausing queue [%s]</info>', $name));
        }

        $jobs->resume($name);

        $this->writeln(sprintf('<info>Queue [%s] has been resumed.</info>', $name));
    }
}
