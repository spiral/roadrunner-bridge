<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Question;
use Spiral\Console\Command;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;

#[AsCommand(
    name: 'rr:jobs:consume',
    description: 'Resumes the consumption of jobs for the specified pipeline in the RoadRunner.'
)]
final class ResumeCommand extends Command
{
    #[Argument(description: 'Pipeline name')]
    #[Question('Provide pipeline name to resume')]
    public string $pipeline;

    /**
     * @throws JobsException
     */
    public function perform(PipelineRegistryInterface $registry): int
    {
        \assert($this->pipeline !== '');

        if ($this->isVerbose()) {
            $this->info(\sprintf('Trying to start consuming pipeline [%s]...', $this->pipeline));
        }

        $queue = $registry->getPipeline($this->pipeline);

        if ($queue->isPaused()) {
            $this->info(\sprintf('Pipeline [%s] has been started consuming tasks.', $this->pipeline));
            $queue->resume();
        } else {
            $this->warning(\sprintf('Pipeline [%s] is not paused.', $this->pipeline));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
