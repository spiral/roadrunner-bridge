<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo;

use PHPUnit\Framework\Constraint\IsEqual;
use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\RoadRunnerBridge\Centrifugo\LogErrorHandler;
use Spiral\Tests\TestCase;

final class LogErrorHandlerTest extends TestCase
{
    public function testHandle(): void
    {
        $error = new \Error('some');

        $reporter = $this->createMock(ExceptionReporterInterface::class);
        $reporter
            ->expects($this->once())
            ->method('report')
            ->with(new IsEqual($error));

        (new LogErrorHandler($reporter))->handle($this->createMock(RequestInterface::class), $error);
    }
}
