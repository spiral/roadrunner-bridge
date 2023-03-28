<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Command;
use Spiral\RoadRunner\Jobs\JobsInterface;

final class ResumeCommand extends Command
{
    protected const SIGNATURE = 'rr:jobs:consume {pipeline : Pipeline name}';
    protected const DESCRIPTION = 'Resumes the consumption of jobs for the specified pipeline in the RoadRunner.';

    public function perform(JobsInterface $jobs): int
    {
        $name = $this->argument('pipeline');

        if ($this->isVerbose()) {
            $this->writeln(\sprintf('<info>Pausing pipeline [%s]</info>', $name));
        }

        $jobs->resume($name);

        $this->writeln(\sprintf('<info>Pipeline [%s] has been resumed.</info>', $name));

        return self::SUCCESS;
    }
}
