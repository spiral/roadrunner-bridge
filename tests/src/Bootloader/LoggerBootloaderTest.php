<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\Tests\TestCase;

final class LoggerBootloaderTest extends TestCase
{
    public function testHandlerBinding(): void
    {
        $this->assertContainerBoundAsSingleton(Handler::class, Handler::class);
    }
}