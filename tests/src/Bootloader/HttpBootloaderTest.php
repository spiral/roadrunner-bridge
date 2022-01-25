<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\RoadRunnerBridge\Http\Dispatcher;
use Spiral\RoadRunnerBridge\Http\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\Http\LogErrorHandler;
use Spiral\Tests\TestCase;

final class HttpBootloaderTest extends TestCase
{
    public function testDispatcherShouldBeRegistered()
    {
        $dispatchers = $this->accessProtected($this->app, 'dispatchers');

        $this->assertCount(1, array_filter($dispatchers, function ($dispatcher) {
            return $dispatcher instanceof Dispatcher;
        }));
    }

    public function testGetsErrorHandlerInterface()
    {
        $this->assertContainerBoundAsSingleton(
            ErrorHandlerInterface::class,
            LogErrorHandler::class
        );
    }
}
