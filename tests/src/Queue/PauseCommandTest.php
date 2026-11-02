<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use RoadRunner\Lock\LockInterface;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\Tests\ConsoleTestCase;
use Spiral\Tests\Queue\Fixtures\MemoryLock;
use Testo\Assert;
use Testo\Test;

final class PauseCommandTest extends ConsoleTestCase
{
    #[Test]
    public function pausePipeline(): void
    {
        $jobs = \Mockery::mock(JobsInterface::class);
        $this->getContainer()->bindSingleton(JobsInterface::class, $jobs, true);

        $jobs->shouldReceive('pause')->once()->with('foo');
        $this->getContainer()->bindSingleton(LockInterface::class, $lock = new MemoryLock(), true);
        $lock->locks['roadrunner-bridge:jobs:pending:foo'] = 'declarer';

        $result = $this->runCommand('rr:jobs:pause', ['pipeline' => 'foo']);
        Assert::string($result)->contains('Pipeline [foo] has been paused.');
        Assert::same($lock->locks, []);
    }
}
