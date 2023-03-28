<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Queue;

use Spiral\RoadRunner\Jobs\DTO\V1\Stat;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\Tests\ConsoleTestCase;

final class ListCommandTest extends ConsoleTestCase
{
    public function testGetsListOfAvailablePipelines()
    {
        $jobs = \Mockery::mock(JobsInterface::class);
        $this->getContainer()->bind(JobsInterface::class, $jobs);

        $jobs->shouldReceive('getIterator')->andReturn(
            new \ArrayIterator([
                'memory' => $queue = \Mockery::mock(QueueInterface::class),
                'amqp' => $amqp = \Mockery::mock(QueueInterface::class),
            ])
        );

        $queue->shouldReceive('getPipelineStat')->once()->andReturn(
            new Stat([
                'pipeline' => 'test',
                'driver' => 'memory',
                'queue' => 'local',
                'ready' => true,
                'active' => 100,
                'delayed' => 55,
                'priority' => 200,
                'reserved' => 8,
            ])
        );

        $amqp->shouldReceive('getPipelineStat')->once()->andReturn(
            new Stat([
                'pipeline' => 'default',
                'driver' => 'amqp',
                'queue' => 'local',
                'ready' => false,
                'active' => 110,
                'delayed' => 88,
                'priority' => 250,
                'reserved' => 56,
            ])
        );

        $this->assertStringContainsString(
            <<<EOL
+---------+--------+----------+-------------+--------------+---------------+-----------+
| Name    | Driver | Priority | Active jobs | Delayed jobs | Reserved jobs | Is active |
+---------+--------+----------+-------------+--------------+---------------+-----------+
| default | amqp   | 250      | 110         | 88           | 56            |  ✖        |
| test    | memory | 200      | 100         | 55           | 8             |  ✓        |
+---------+--------+----------+-------------+--------------+---------------+-----------+
EOL,
            $this->runCommand('rr:jobs:list')
        );
    }
}
