<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Queue;

use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\Tests\ConsoleTestCase;

final class PauseCommandTest extends ConsoleTestCase
{
    public function testPausePipeline()
    {
        $jobs = \Mockery::mock(JobsInterface::class);
        $this->container->bind(JobsInterface::class, $jobs);

        $jobs->shouldReceive('pause')->once()->with('foo');

        $result = $this->runCommand('roadrunner:pause', ['pipeline' => 'foo']);
        $this->assertStringContainsString('Pipeline [foo] has been paused.', $result);
    }
}
