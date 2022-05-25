<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Queue;

use Spiral\RoadRunner\Jobs\DTO\V1\Stat;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\Tests\ConsoleTestCase;

final class ListCommandTest extends ConsoleTestCase
{
    public function testGetsListOfAvailablePipelines()
    {
        $jobs = \Mockery::mock(JobsInterface::class);
        $this->getContainer()->bind(JobsInterface::class, $jobs);

        $jobs->shouldReceive('getIterator')->once()->andReturn(
            new \ArrayIterator([
                'memory' => $memory = \Mockery::mock(QueueInterface::class),
                'amqp' => $amqp = \Mockery::mock(QueueInterface::class),
            ])
        );

        $memory->shouldReceive('getDefaultOptions')->once()->andReturn(new Options(55, 200));
        $memory->shouldReceive('getPipelineStat')->once()->andReturn(
            new Stat([
                'pipeline' => 'test',
                'driver' => 'memory',
                'queue' => 'local',
                'ready' => true,
                'active' => 100,
                'delayed' => 5,
                'reserved' => 8,
            ])
        );
        $memory->shouldReceive('isPaused')->once()->andReturnFalse();

        $amqp->shouldReceive('getDefaultOptions')->once()->andReturn(new Options(88, 250));
        $amqp->shouldReceive('getPipelineStat')->once()->andReturn(
            new Stat([
                'pipeline' => 'default',
                'driver' => 'amqp',
                'queue' => 'local',
                'ready' => true,
                'active' => 110,
                'delayed' => 17,
                'reserved' => 56,
            ])
        );
        $amqp->shouldReceive('isPaused')->once()->andReturnTrue();

        $this->assertStringContainsString(
            <<<EOL
+---------+--------+---------------+----------+-------------+--------------+---------------+-----------+
| Name    | Driver | Default delay | Priority | Active jobs | Delayed jobs | Reserved jobs | Is active |
+---------+--------+---------------+----------+-------------+--------------+---------------+-----------+
| test    | memory | 55            | 200      | 100         | 5            | 8             |  ✓        |
| default | amqp   | 88            | 250      | 110         | 17           | 56            |  ✖        |
+---------+--------+---------------+----------+-------------+--------------+---------------+-----------+
EOL,
            $this->runCommand('roadrunner:list')
        );
    }
}
