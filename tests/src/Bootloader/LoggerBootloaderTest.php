<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Monolog\Handler\ErrorLogHandler;
use Spiral\Monolog\Config\MonologConfig;
use Spiral\Tests\TestCase;

/**
 * @coversDefaultClass \Spiral\RoadRunnerBridge\Bootloader\LoggerBootloader
 */
final class LoggerBootloaderTest extends TestCase
{
    public function testRegisterErrorLogHandlerRunWithoutRR(): void
    {
        $config = $this->getConfig(MonologConfig::CONFIG);

        $this->assertArrayHasKey('roadrunner', $config['handlers']);
        $this->assertInstanceOf(ErrorLogHandler::class, $config['handlers']['roadrunner'][0]);
    }
}
