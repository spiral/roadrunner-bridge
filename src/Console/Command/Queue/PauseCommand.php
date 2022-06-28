<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Command;
use Spiral\RoadRunner\Jobs\JobsInterface;

final class PauseCommand extends Command
{
    protected const SIGNATURE = 'roadrunner:pause {pipeline : Pipeline name}';
    protected const DESCRIPTION = 'Pause consuming jobs for pipeline with given name';

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
