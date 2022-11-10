<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use RoadRunner\Logger\Logger as RoadRunnerLogger;

class Handler extends AbstractProcessingHandler
{
    public const FORMAT = "%message% %context% %extra%\n";

    public function __construct(
        private readonly RoadRunnerLogger $logger,
        string|FormatterInterface $formatter = self::FORMAT
    ) {
        parent::__construct();

        if (\is_string($formatter)) {
            $formatter = new LineFormatter($formatter);
        }

        $this->setFormatter($formatter);
    }

    protected function write(array $record): void
    {
        $message = $record['formatted'];

        match ($record['level']) {
            Logger::ERROR, Logger::CRITICAL => $this->logger->error($message),
            Logger::WARNING, Logger::ALERT, Logger::EMERGENCY => $this->logger->warning($message),
            Logger::INFO, Logger::NOTICE => $this->logger->info($message),
            Logger::DEBUG => $this->logger->debug($message),
        };
    }
}
