<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Handler\HandlerInterface;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use RoadRunner\Logger\Logger as RoadRunnerLogger;

final class Handler extends AbstractProcessingHandler
{
    public const FORMAT = "%message% %context% %extra%\n";

    public function __construct(
        private readonly RoadRunnerLogger $logger,
        private readonly ?HandlerInterface $fallbackHandler = null,
        string|FormatterInterface $formatter = self::FORMAT,
    ) {
        parent::__construct();

        if (\is_string($formatter)) {
            $formatter = new LineFormatter($formatter);
        }

        $this->setFormatter($formatter);
    }

    public function handle(array|LogRecord $record): bool
    {
        if ($this->fallbackHandler !== null) {
            return $this->fallbackHandler->handle($record);
        }

        return parent::handle($record);
    }

    protected function write(array|LogRecord $record): void
    {
        /** @psalm-suppress InvalidArgument */
        $message = $record['formatted'];
        \assert(\is_string($message) || $message instanceof \Stringable);

        $level = $record['level'] instanceof Level ? $record['level']->value : $record['level'];

        /** @psalm-suppress DeprecatedConstant */
        match ($level) {
            Logger::ERROR, Logger::CRITICAL, Logger::ALERT, Logger::EMERGENCY => $this->logger->error($message),
            Logger::WARNING => $this->logger->warning($message),
            Logger::INFO, Logger::NOTICE => $this->logger->info($message),
            Logger::DEBUG => $this->logger->debug($message),
        };
    }
}
