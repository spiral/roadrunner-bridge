<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Queue;

use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\Tests\ConsoleTestCase;

final class PauseCommandTest extends ConsoleTestCase
{
    public function testPausePipeline(): void
    {
        $jobs = \Mockery::mock(JobsInterface::class);
        $this->getContainer()->bindSingleton(JobsInterface::class, $jobs, true);

        $jobs->shouldReceive('pause')->once()->with('foo');

        $result = $this->runCommand('rr:jobs:pause', ['pipeline' => 'foo']);
        $this->assertStringContainsString('Pipeline [foo] has been paused.', $result);
    }
}
