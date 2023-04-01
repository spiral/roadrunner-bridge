<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Command;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\JobsInterface;

final class PauseCommand extends Command
{
    protected const SIGNATURE = 'rr:jobs:pause {pipeline : Pipeline name}';
    protected const DESCRIPTION = 'Pauses the consumption of jobs for the specified pipeline in the RoadRunner.';

    /**
     * @throws JobsException
     */
    public function perform(JobsInterface $jobs): int
    {
        $name = $this->argument('pipeline');

        if ($this->isVerbose()) {
            $this->info(\sprintf('Pausing pipeline [%s]...', $name));
        }

        $jobs->pause($name);

        $this->info(\sprintf('Pipeline [%s] has been paused.', $name));

        return self::SUCCESS;
    }
}
