<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue\Internal\Command;

use RoadRunner\Lock\LockInterface;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Question;
use Spiral\Console\Command;
use Spiral\Goridge\Exception\GoridgeException;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunnerBridge\Queue\Internal\PipelineLocks;

/**
 * @internal
 */
#[AsCommand(
    name: 'rr:jobs:pause',
    description: 'Pauses the consumption of jobs for the specified pipeline in the RoadRunner.',
)]
final class PauseCommand extends Command
{
    /** @psalm-suppress PropertyNotSetInConstructor Set by the console from the command argument */
    #[Argument(description: 'Pipeline name')]
    #[Question('Provide pipeline name to resume')]
    public string $pipeline;

    /**
     * @throws JobsException
     */
    public function perform(JobsInterface $jobs, LockInterface $lock, PipelineLocks $locks): int
    {
        \assert($this->pipeline !== '');

        if ($this->isVerbose()) {
            $this->info(\sprintf('Pausing pipeline [%s]...', $this->pipeline));
        }

        $jobs->pause($this->pipeline);

        # A pending marker makes the next booting jobs worker resume the pipeline.
        try {
            $lock->forceRelease($locks->pending($this->pipeline));
        } catch (GoridgeException $e) {
            $this->warning(\sprintf(
                'The pipeline may be resumed when a jobs worker boots: unable to release its pending marker. Reason: %s',
                $e->getMessage(),
            ));
        }

        $this->info(\sprintf('Pipeline [%s] has been paused.', $this->pipeline));

        return self::SUCCESS;
    }
}
