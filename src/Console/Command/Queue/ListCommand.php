<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Command;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Queue;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableCellStyle;

#[AsCommand(
    name: 'rr:jobs:list',
    description: 'Displays a list of available job pipelines for the RoadRunner.')
]
final class ListCommand extends Command
{
    /**
     * @throws JobsException
     */
    public function perform(JobsInterface $jobs): int
    {
        $queues = \iterator_to_array($jobs->getIterator());

        if ($queues === []) {
            $this->info('No job pipelines are currently declared for the RoadRunner.');

            return self::SUCCESS;
        }

        $queues = \array_map(static function (QueueInterface $queue): array {
            \assert($queue instanceof Queue);
            $stat = $queue->getPipelineStat();

            $fontColor = $stat->getReady() ? 'green' : 'gray';
            $defaultColor = $stat->getReady() ? 'default' : 'gray';
            $activeFont = $stat->getReady() ? 'bold' : '';

            return [
                'name' => new TableCell($stat->getPipeline(), [
                    'style' => new TableCellStyle(['fg' => $fontColor, 'options' => $activeFont]),
                ]),
                'driver' => new TableCell($stat->getDriver(), [
                    'style' => new TableCellStyle(
                        ['fg' => $defaultColor, 'options' => $activeFont]
                    ),
                ]),
                'priority' => new TableCell((string)$stat->getPriority(), [
                    'style' => new TableCellStyle(
                        ['fg' => $defaultColor, 'options' => $activeFont]
                    ),
                ]),
                'active_jobs' => new TableCell((string)$stat->getActive(), [
                    'style' => new TableCellStyle(
                        ['fg' => $stat->getActive() > 0 ? 'green' : $defaultColor, 'options' => $activeFont]
                    ),
                ]),
                'delayed_jobs' => new TableCell((string)$stat->getDelayed(), [
                    'style' => new TableCellStyle(
                        ['fg' => $stat->getDelayed() > 0 ? 'green' : $defaultColor, 'options' => $activeFont]
                    ),
                ]),
                'reserved_jobs' => new TableCell((string)$stat->getReserved(), [
                    'style' => new TableCellStyle(
                        ['fg' => $stat->getReserved() > 0 ? 'green' : $defaultColor, 'options' => $activeFont]
                    ),
                ]),
                'is_active' => $stat->getReady() ? '<fg=green> ✓ </>' : '<fg=red> ✖ </>',
            ];
        }, $queues);

        \ksort($queues);

        $table = new Table($this->output);

        $table->setHeaders(
            ['Name', 'Driver', 'Priority', 'Active jobs', 'Delayed jobs', 'Reserved jobs', 'Is active',],
        );

        foreach ($queues as $data) {
            $table->addRow($data);
        }

        $table->render();

        return self::SUCCESS;
    }
}
