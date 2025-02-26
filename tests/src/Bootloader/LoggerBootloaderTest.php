<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use RoadRunner\Logger\Logger;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\Monolog\Bootloader\MonologBootloader;
use Spiral\Monolog\Config\MonologConfig;
use Spiral\RoadRunnerBridge\Bootloader\LoggerBootloader;
use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\RoadRunnerBridge\Logger\RoadRunnerLogsMode;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Tests\TestCase;

/**
 * @coversDefaultClass \Spiral\RoadRunnerBridge\Bootloader\LoggerBootloader
 */
final class LoggerBootloaderTest extends TestCase
{
    public function testHandlerIsRegisteredInMonolog(): void
    {
        $env = $this->createMock(EnvironmentInterface::class);
        (new LoggerBootloader())
            ->init(
                new MonologBootloader(
                    $this->createMock(ConfiguratorInterface::class),
                    $env,
                ),
                new Logger($this->createMock(RPCInterface::class)),
                RoadRunnerMode::Grpc,
                $env,
                RoadRunnerLogsMode::Production,
            );

        $config = $this->getConfig(MonologConfig::CONFIG);

        $this->assertArrayHasKey('roadrunner', $config['handlers']);
        $this->assertInstanceOf(Handler::class, $config['handlers']['roadrunner'][0]);
    }
}
