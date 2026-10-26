<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\RoadRunnerBridge\Http\Internal\Dispatcher;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class HttpBootloaderTest extends TestCase
{
    #[Test]
    public function dispatcherShouldBeRegistered(): void
    {
        $dispatchers = $this->getRegisteredDispatchers();

        Assert::count(\array_filter($dispatchers, static function ($dispatcher) {
            return $dispatcher === Dispatcher::class;
        }), 1);
    }

    #[Test]
    public function oldRoadRunnerDispatchersShouldNotBeLoaded(): void
    {
        $this->assertDispatcherMissed('Spiral\Http\LegacyRrDispatcher');
        $this->assertDispatcherMissed('Spiral\Http\RrDispatcher');
    }

    #[Test]
    public function oldRoadRunnerBootloadersShouldNotBeLoaded(): void
    {
        $this->assertBootloaderMissed('Spiral\Bootloader\ServerBootloader');
        $this->assertBootloaderMissed('Spiral\Bootloader\Server\LegacyRoadRunnerBootloader');
        $this->assertBootloaderMissed('Spiral\Bootloader\Server\RoadRunnerBootloader');
    }
}
