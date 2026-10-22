<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Queue;

use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\Tests\ConsoleTestCase;
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

        $result = $this->runCommand('rr:jobs:pause', ['pipeline' => 'foo']);
        Assert::string($result)->contains('Pipeline [foo] has been paused.');
    }
}
