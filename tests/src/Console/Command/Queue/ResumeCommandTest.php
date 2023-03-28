<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Queue;

use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\Tests\ConsoleTestCase;

final class ResumeCommandTest extends ConsoleTestCase
{
    public function testPausePipeline()
    {
        $jobs = \Mockery::mock(JobsInterface::class);
        $this->getContainer()->bind(JobsInterface::class, $jobs);

        $jobs->shouldReceive('resume')->once()->with('foo');

        $result = $this->runCommand('rr:jobs:consume', ['pipeline' => 'foo']);
        $this->assertStringContainsString('Pipeline [foo] has been resumed.', $result);
    }
}
