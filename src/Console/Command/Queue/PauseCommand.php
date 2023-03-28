<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Command;
use Spiral\RoadRunner\Jobs\JobsInterface;

final class PauseCommand extends Command
{
    protected const SIGNATURE = 'rr:jobs:pause {pipeline : Pipeline name}';
    protected const DESCRIPTION = 'Pauses the consumption of jobs for the specified pipeline in the RoadRunner.';

    public function perform(JobsInterface $jobs): int
    {
        $name = $this->argument('pipeline');

        if ($this->isVerbose()) {
            $this->writeln(\sprintf('<info>Pausing pipeline [%s]</info>', $name));
        }

        $jobs->pause($name);

        $this->writeln(\sprintf('<info>Pipeline [%s] has been paused.</info>', $name));

        return self::SUCCESS;
    }
}
