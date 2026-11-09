<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Http\Internal;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Attribute\DispatcherScope;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\Environment\AppEnvironment;
use Spiral\Boot\FinalizerInterface;
use Spiral\Exceptions\ExceptionHandlerInterface;
use Spiral\Exceptions\Verbosity;
use Spiral\Http\Http;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunnerBridge\Config\HttpWorkerConfig;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

/**
 * @internal
 */
#[DispatcherScope(scope: 'http')]
final readonly class Dispatcher implements DispatcherInterface
{
    public function __construct(
        private PSR7WorkerInterface $worker,
        private Http $http,
        private ExceptionHandlerInterface $errorHandler,
        private ResponseFactoryInterface $responseFactory,
        private FinalizerInterface $finalizer,
        private AppEnvironment $environment,
        private HttpWorkerConfig $config,
    ) {}

    public static function canServe(RoadRunnerMode $mode): bool
    {
        return \PHP_SAPI === 'cli' && $mode === RoadRunnerMode::Http;
    }

    #[\Override]
    public function serve(): void
    {
        $worker = $this->worker;
        $populateServer = $this->config->shouldPopulateServer();
        // The interface method takes no flag: a custom or decorated worker keeps its own $_SERVER handling.
        $wReq = $worker instanceof PSR7Worker
            ? static fn(): ?ServerRequestInterface => $worker->waitRequest($populateServer)
            : static fn(): ?ServerRequestInterface => $worker->waitRequest();
        do {
            $failure = null;
            try {
                // Wrapped in do/while and try/catch to prevent Worker restart if request parsing is failed
                //
                do {
                    $request = $wReq();
                    if ($request === null) {
                        // Worker got Close signal, stop serving
                        return;
                    }

                    try {
                        $response = $this->http->handle($request);
                        $worker->respond($response);
                    } catch (\Throwable $failure) {
                        $worker->respond($this->errorToResponse($failure));
                        unset($failure);
                    } finally {
                        $this->finalizer->finalize(false);
                    }
                } while (true);
            } catch (\Throwable $e) {
                // Don't continue if the respond() call was failed
                if (isset($failure)) {
                    break;
                }

                $worker->respond($this->errorToResponse($e));
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
