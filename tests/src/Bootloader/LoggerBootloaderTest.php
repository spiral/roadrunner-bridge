<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\Monolog\Config\MonologConfig;
use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\Tests\TestCase;

final class LoggerBootloaderTest extends TestCase
{
    public function testHandlerBinding(): void
    {
        $this->assertContainerBoundAsSingleton(Handler::class, Handler::class);
    }

    public function testHandlerIsRegisteredInMonolog(): void
    {
        $config = $this->getConfig(MonologConfig::CONFIG);

        $this->assertArrayHasKey('roadrunner', $config['handlers']);
        $this->assertInstanceOf(Handler::class, $config['handlers']['roadrunner'][0]);
    }
}
