<?php

declare(strict_types=1);

namespace Spiral\Tests\Logger;

use Monolog\Level;
use RoadRunner\Logger\Logger;
use Spiral\Boot\Environment;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunnerBridge\Logger\Formatter\RoadRunnerJsonFormatter;
use Spiral\RoadRunnerBridge\Logger\LoggerHandler;
use Spiral\RoadRunnerBridge\Logger\LoggerHandlerFactory;
use Spiral\RoadRunnerBridge\Logger\RoadRunnerLogsMode;
use Spiral\Tests\TestCase;

final class LoggerHandlerFactoryTest extends TestCase
{
    public function testCreateLoggerHandler(): void
    {
        $rpc = $this->createMock(RPCInterface::class);

        $factory = new LoggerHandlerFactory(
            new Logger($rpc),
            RoadRunnerLogsMode::Production,
            new Environment(),
        );

        $logger = $factory->create(Level::Error);

        $this->assertInstanceOf(LoggerHandler::class, $logger);
        $this->assertInstanceOf(RoadRunnerJsonFormatter::class, $logger->getFormatter());
        $this->assertEquals(Level::Error, $logger->getLevel());
    }
}
