<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use RoadRunner\Logger\Logger as RoadRunnerLogger;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

final class Handler extends AbstractProcessingHandler
{
    public const FORMAT = "%message% %context% %extra%\n";

    private ?HandlerInterface $handler = null;

    public function __construct(
        private readonly RoadRunnerLogger $logger,
        private readonly RoadRunnerMode $mode,
        string|FormatterInterface $formatter = self::FORMAT,
    ) {
        parent::__construct();

        if ($this->mode === RoadRunnerMode::Unknown) {
            $this->handler = new ErrorLogHandler();
        }

        if (\is_string($formatter)) {
            $formatter = new LineFormatter($formatter);
        }

        $this->setFormatter($formatter);
    }

    public function handle(array $record): bool
    {
        if ($this->handler !== null) {
            return $this->handler->handle($record);
        }

        return parent::handle($record);
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
