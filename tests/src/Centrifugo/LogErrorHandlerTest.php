<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo;

use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\RoadRunnerBridge\Centrifugo\Internal\LogErrorHandler;
use Spiral\Tests\TestCase;
use Testo\Test;

final class LogErrorHandlerTest extends TestCase
{
    #[Test]
    public function handle(): void
    {
        $error = new \Error('some');

        $reporter = \Mockery::mock(ExceptionReporterInterface::class);
        $reporter->shouldReceive('report')->once()->with(\Mockery::isEqual($error), \Mockery::andAnyOtherArgs());

        (new LogErrorHandler($reporter))->handle(\Mockery::mock(RequestInterface::class)->shouldIgnoreMissing(), $error);
    }
}
