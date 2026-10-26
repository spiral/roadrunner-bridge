<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Monolog\Handler\ErrorLogHandler;
use Monolog\Level;
use Spiral\Monolog\Config\MonologConfig;
use Spiral\RoadRunnerBridge\Logger\Handler;
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
}
