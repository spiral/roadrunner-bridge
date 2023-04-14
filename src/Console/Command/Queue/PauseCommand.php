<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Question;
use Spiral\Console\Command;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\JobsInterface;

#[AsCommand(
    name: 'rr:jobs:pause',
    description: 'Pauses the consumption of jobs for the specified pipeline in the RoadRunner.',
)]
final class PauseCommand extends Command
{
    #[Argument(description: 'Pipeline name')]
    #[Question('Provide pipeline name to resume')]
    public string $pipeline;

    /**
     * @throws JobsException
     */
    public function perform(JobsInterface $jobs): int
    {
        \assert($this->pipeline !== '');

        if ($this->isVerbose()) {
            $this->info(\sprintf('Pausing pipeline [%s]...', $this->pipeline));
        }

        $jobs->pause($this->pipeline);

        $this->info(\sprintf('Pipeline [%s] has been paused.', $this->pipeline));

        return self::SUCCESS;
    }
}
