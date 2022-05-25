<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\RoadRunnerBridge\Http\Dispatcher;
use Spiral\RoadRunnerBridge\Http\ErrorHandlerInterface;
use Spiral\RoadRunnerBridge\Http\LogErrorHandler;
use Spiral\Tests\TestCase;

final class HttpBootloaderTest extends TestCase
{
    public function testDispatcherShouldBeRegistered(): void
    {
        $dispatchers = $this->getRegisteredDispatchers();

        $this->assertCount(
            1,
            array_filter($dispatchers, function ($dispatcher) {
                return $dispatcher === Dispatcher::class;
            })
        );
    }

    public function testGetsErrorHandlerInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            ErrorHandlerInterface::class,
            LogErrorHandler::class
        );
    }

    public function testOldRoadRunnerDispatchersShouldNotBeLoaded(): void
    {
        $this->assertDispatcherMissed('Spiral\Http\LegacyRrDispatcher');
        $this->assertDispatcherMissed('Spiral\Http\RrDispatcher');
    }

    public function testOldRoadRunnerBootloadersShouldNotBeLoaded()
    {
        $this->assertBootloaderMissed('Spiral\Bootloader\ServerBootloader');
        $this->assertBootloaderMissed('Spiral\Bootloader\Server\LegacyRoadRunnerBootloader');
        $this->assertBootloaderMissed('Spiral\Bootloader\Server\RoadRunnerBootloader');
    }
}
