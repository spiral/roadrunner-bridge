<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo\Internal;

use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ErrorHandlerInterface;

/**
 * @internal
 */
final class LogErrorHandler implements ErrorHandlerInterface
{
    public function __construct(
        private readonly ExceptionReporterInterface $reporter,
    ) {}

    public function handle(RequestInterface $request, \Throwable $e): void
    {
        $this->reporter->report($e);
    }
}
