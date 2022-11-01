<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use RoadRunner\Logger\Logger as RoadRunnerLogger;

class Handler extends AbstractProcessingHandler
{
    public function __construct(private readonly RoadRunnerLogger $logger)
    {
        parent::__construct();
    }

    protected function write(array $record): void
    {
        $message = $record['message'] ?? $record['formatted'];

        switch ($record['level']) {
            case Logger::ERROR:
                $this->logger->error($message);
                break;
            case Logger::WARNING:
                $this->logger->warning($message);
                break;
            case Logger::INFO:
                $this->logger->info($message);
                break;
            case Logger::DEBUG:
                $this->logger->debug($message);
                break;
            default:
                $this->logger->log($message);
        }
    }
}