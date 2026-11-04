<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Spiral\Monolog\Config\MonologConfig;
use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\RoadRunnerBridge\Logger\RawHandler;
use Spiral\Testing\Attribute\Env;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class LoggerBootloaderTest extends TestCase
{
    #[Env('RR_MODE', 'http')]
    #[Test]
    public function registerHandlerRunInsideRREnvironment(): void
    {
        $config = $this->getConfig(MonologConfig::CONFIG);

        Assert::array($config['handlers'])->hasKeys('roadrunner');
        $handler = $config['handlers']['roadrunner'][0];
        Assert::instanceOf($handler, Handler::class);
        // MONOLOG_DEFAULT_LEVEL=INFO
        Assert::equals($handler->getLevel(), Level::Info);
    }

    #[Env('RR_MODE', 'http')]
    #[Env('RR_LOGGER_MODE', 'production')]
    #[Env('RR_LOGGER_PREFIX', 'app-')]
    #[Test]
    public function registerRawHandlerInProductionMode(): void
    {
        $handler = $this->roadRunnerHandler();

        Assert::instanceOf($handler, RawHandler::class);
        Assert::equals($handler->getLevel(), Level::Info);
        $line = $handler->getFormatter()->format(new LogRecord(new \DateTimeImmutable(), 'orders', Level::Warning, 'm'));
        Assert::same($line['logger'], 'app-orders');
        Assert::same($line['level'], 'warning');
    }

    #[Env('RR_MODE', 'http')]
    #[Env('RR_LOGGER_MODE', 'dev')]
    #[Test]
    public function registerRawHandlerInDevelopmentMode(): void
    {
        $handler = $this->roadRunnerHandler();

        Assert::instanceOf($handler, RawHandler::class);
        $line = $handler->getFormatter()->format(new LogRecord(new \DateTimeImmutable(), 'orders', Level::Warning, 'm'));
        Assert::same($line['logger'], 'orders');
        Assert::same($line['level'], 'WARNING');
    }

    #[Test]
    public function registerErrorLogHandlerRunWithoutRR(): void
    {
        $config = $this->getConfig(MonologConfig::CONFIG);

        Assert::array($config['handlers'])->hasKeys('roadrunner');
        Assert::instanceOf($config['handlers']['roadrunner'][0], ErrorLogHandler::class);
    }

    #[Env('RR_MODE', 'http')]
    #[Env('MONOLOG_DEFAULT_LEVEL', 'ALERT')]
    #[Test]
    public function defaultLoggerLevel(): void
    {
        $config = $this->getConfig(MonologConfig::CONFIG);

        Assert::array($config['handlers'])->hasKeys('roadrunner');
        Assert::count($config['handlers'], 1);
        /** @var Handler $handler */
        $handler = $config['handlers']['roadrunner'][0];
        Assert::instanceOf($handler, Handler::class);
        Assert::equals($handler->getLevel(), Level::Alert);
    }

    private function roadRunnerHandler(): HandlerInterface
    {
        $handler = $this->getConfig(MonologConfig::CONFIG)['handlers']['roadrunner'][0];
        Assert::instanceOf($handler, HandlerInterface::class);

        return $handler;
    }
}
