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

        # Under the declaration lock, so a process declaring the pipeline cannot resume it right after.
        $declare = $locks->declare($this->pipeline);
        $id = $this->quietly(
            static fn(): string|false => $lock->lock($declare, null, PipelineLocks::TTL, PipelineLocks::PUSH_WAIT),
        );

        try {
            $jobs->pause($this->pipeline);

            # A pending marker makes the next booting jobs worker resume the pipeline.
            $this->quietly(fn(): bool => $lock->forceRelease($locks->pending($this->pipeline)));
        } finally {
            if (\is_string($id) && $id !== '') {
                $this->quietly(static fn(): bool => $lock->release($declare, $id));
            }
        }

        $this->info(\sprintf('Pipeline [%s] has been paused.', $this->pipeline));

        return self::SUCCESS;
    }

    /**
     * Runs a lock call; a failure only warns that a booting jobs worker may still resume the pipeline.
     */
    private function quietly(\Closure $call): mixed
    {
        try {
            return $call();
        } catch (GoridgeException $e) {
            $this->warning(\sprintf(
                'A booting jobs worker may resume the pipeline: the RoadRunner lock call failed (the RoadRunner build must include the `lock` plugin). Reason: %s',
                $e->getMessage(),
            ));

            return null;
        }
    }
}
