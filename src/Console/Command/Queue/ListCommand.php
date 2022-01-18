<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Command;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Symfony\Component\Console\Helper\Table;

final class ListCommand extends Command
{
    protected const NAME = 'queue:list';
    protected const DESCRIPTION = 'List available queue connections';

    public function perform(JobsInterface $jobs): void
    {
        $table = new Table($this->output);

        $table->setHeaders(['Name', 'Default delay', 'Priority', 'Is active']);

        foreach ($jobs as $queue) {
            $options = $queue->getDefaultOptions();

            $table->addRow([
                $queue->getName(),
                $options->getDelay(),
                $options->getPriority(),
                $queue->isPaused() ? '<fg=red> ✖ </>' : '<fg=green> ✓ </>'
            ]);
        }

        $table->render();
    }
}
