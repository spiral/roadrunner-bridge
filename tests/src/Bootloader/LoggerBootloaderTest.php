<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Level;
use Spiral\Monolog\Config\MonologConfig;
use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\Testing\Attribute\Env;
use Spiral\Tests\TestCase;

/**
 * @coversDefaultClass \Spiral\RoadRunnerBridge\Bootloader\LoggerBootloader
 */
final class LoggerBootloaderTest extends TestCase
{
    #[Env('RR_MODE', 'http')]
    public function testRegisterHandlerRunInsideRREnvironment(): void
    {
        $config = $this->getConfig(MonologConfig::CONFIG);

        $this->assertArrayHasKey('roadrunner', $config['handlers']);
        $handler = $config['handlers']['roadrunner'][0];
        $this->assertInstanceOf(Handler::class, $handler);
        // MONOLOG_DEFAULT_LEVEL=INFO
        $this->assertEquals(Level::Info, $handler->getLevel());
    }

    public function testRegisterErrorLogHandlerRunWithoutRR(): void
    {
        $config = $this->getConfig(MonologConfig::CONFIG);

        $this->assertArrayHasKey('roadrunner', $config['handlers']);
        $this->assertInstanceOf(ErrorLogHandler::class, $config['handlers']['roadrunner'][0]);
    }

    #[Env('RR_MODE', 'http')]
    #[Env('MONOLOG_DEFAULT_LEVEL', 'ALERT')]
    public function testDefaultLoggerLevel(): void
    {
        $config = $this->getConfig(MonologConfig::CONFIG);

        $this->assertArrayHasKey('roadrunner', $config['handlers']);
        $this->assertCount(1, $config['handlers']);
        /** @var Handler $handler */
        $handler = $config['handlers']['roadrunner'][0];
        $this->assertInstanceOf(Handler::class, $handler);
        $this->assertEquals(Level::Alert, $handler->getLevel());
    }
}
