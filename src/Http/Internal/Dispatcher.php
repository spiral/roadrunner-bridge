<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Http\Internal;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Spiral\Attribute\DispatcherScope;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\Environment\AppEnvironment;
use Spiral\Boot\FinalizerInterface;
use Spiral\Exceptions\ExceptionHandlerInterface;
use Spiral\Exceptions\Verbosity;
use Spiral\Http\Http;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

/**
 * @internal
 */
#[DispatcherScope(scope: 'http')]
final class Dispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly PSR7WorkerInterface $worker,
        private readonly Http $http,
        private readonly ExceptionHandlerInterface $errorHandler,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly FinalizerInterface $finalizer,
        private readonly AppEnvironment $environment,
    ) {}

    public static function canServe(RoadRunnerMode $mode): bool
    {
        return \PHP_SAPI === 'cli' && $mode === RoadRunnerMode::Http;
    }

    public function serve(): void
    {
        do {
            try {
                // Wrapped in do/while and try/catch to prevent Worker restart if request parsing is failed
                //
                while ($request = $this->worker->waitRequest()) {
                    try {
                        $response = $this->http->handle($request);
                        $this->worker->respond($response);
                    } catch (\Throwable $failure) {
                        $this->worker->respond($this->errorToResponse($failure));
                        unset($failure);
                    } finally {
                        $this->finalizer->finalize(false);
                    }
                }
            } catch (\Throwable $e) {
                // Don't continue if the respond() call was failed
                if (isset($failure)) {
                    break;
                }

                $this->worker->respond($this->errorToResponse($e));
            }
        } while (true);
    }

    protected function errorToResponse(\Throwable $e): ResponseInterface
    {
        $this->errorHandler->report($e);

        // Reporting system (non handled) exception directly to the client
        $response = $this->responseFactory->createResponse(500);

        // Add details to the response body in non-production environment
        $this->environment->isProduction() or $response
            ->getBody()
            ->write($this->errorHandler->render($e, Verbosity::VERBOSE));

        return $response;
    }
}
