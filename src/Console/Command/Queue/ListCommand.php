<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Command;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Queue;
use Symfony\Component\Console\Helper\Table;

final class ListCommand extends Command
{
    protected const NAME = 'roadrunner:list';
    protected const DESCRIPTION = 'List available roadrunner pipelines';

    public function perform(JobsInterface $jobs): int
    {
        $queues = \iterator_to_array($jobs->getIterator());

        if ($queues === []) {
            return self::SUCCESS;
        }

        $table = new Table($this->output);

        $table->setHeaders(
            ['Name', 'Driver', 'Default delay', 'Priority', 'Active jobs', 'Delayed jobs', 'Reserved jobs', 'Is active']
        );

        foreach ($queues as $queue) {
            /** @var Queue $queue */

            $options = $queue->getDefaultOptions();
            $stat = $queue->getPipelineStat();

            $table->addRow([
                $stat->getPipeline(),
                $stat->getDriver(),
                $options->getDelay(),
                $options->getPriority(),
                $stat->getActive(),
                $stat->getDelayed(),
                $stat->getReserved(),
                $queue->isPaused() ? '<fg=red> ✖ </>' : '<fg=green> ✓ </>',
            ]);
        }

        $table->render();

        return self::SUCCESS;
    }
}
