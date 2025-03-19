<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\RoadRunnerBridge\Http\Internal\Dispatcher;
use Spiral\Tests\TestCase;

final class HttpBootloaderTest extends TestCase
{
    public function testDispatcherShouldBeRegistered(): void
    {
        $dispatchers = $this->getRegisteredDispatchers();

        $this->assertCount(
            1,
            \array_filter($dispatchers, static function ($dispatcher) {
                return $dispatcher === Dispatcher::class;
            }),
        );
    }

    public function testOldRoadRunnerDispatchersShouldNotBeLoaded(): void
    {
        $this->assertDispatcherMissed('Spiral\Http\LegacyRrDispatcher');
        $this->assertDispatcherMissed('Spiral\Http\RrDispatcher');
    }

    public function testOldRoadRunnerBootloadersShouldNotBeLoaded(): void
    {
        $this->assertBootloaderMissed('Spiral\Bootloader\ServerBootloader');
        $this->assertBootloaderMissed('Spiral\Bootloader\Server\LegacyRoadRunnerBootloader');
        $this->assertBootloaderMissed('Spiral\Bootloader\Server\RoadRunnerBootloader');
    }
}
