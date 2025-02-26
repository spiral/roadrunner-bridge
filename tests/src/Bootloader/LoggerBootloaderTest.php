<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Monolog\Handler\ErrorLogHandler;
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
        $this->assertInstanceOf(Handler::class, $config['handlers']['roadrunner'][0]);
    }

    public function testRegisterErrorLogHandlerRunWithoutRR(): void
    {
        $config = $this->getConfig(MonologConfig::CONFIG);

        $this->assertArrayHasKey('roadrunner', $config['handlers']);
        $this->assertInstanceOf(ErrorLogHandler::class, $config['handlers']['roadrunner'][0]);
    }
}
