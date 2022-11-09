<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo;

use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\Exceptions\ExceptionReporterInterface;

final class LogErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly ExceptionReporterInterface $reporter
    ) {
    }

    public function handle(RequestInterface $request, \Throwable $e): void
    {
        $this->reporter->report($e);
    }
}