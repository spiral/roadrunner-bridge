<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Command;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Symfony\Component\Console\Input\InputArgument;

final class ResumeCommand extends Command
{
    protected const SIGNATURE = 'roadrunner:resume {pipeline : Pipeline name}';
    protected const DESCRIPTION = 'Resume consuming jobs for pipeline with given name';

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
