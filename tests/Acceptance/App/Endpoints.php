<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\App;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Spiral\Cache\CacheStorageProviderInterface;
use Spiral\Queue\QueueInterface;
use Spiral\RoadRunner\Metrics\MetricsInterface;
use Spiral\Tests\Acceptance\App\Job\RecordJob;

/**
 * HTTP entry points the acceptance tests reach the other RoadRunner plugins through.
 */
final class Endpoints implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responses,
        private readonly ContainerInterface $container,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $query = $request->getQueryParams();

        return match ($request->getUri()->getPath()) {
            '/hello' => $this->text('Hello, ' . ($query['name'] ?? 'world'))
                ->withHeader('X-Method', $request->getMethod()),
            '/echo' => $this->text((string) $request->getBody())
                ->withHeader('Content-Type', $request->getHeaderLine('Content-Type')),
            '/cache/set' => $this->text(\var_export($this->cache()->set($query['key'], $query['value']), true)),
            '/cache/get' => $this->text((string) $this->cache()->get($query['key'], '(missing)')),
            '/jobs/push' => $this->text($this->container->get(QueueInterface::class)->push(
                RecordJob::class,
                ['name' => $query['name'], 'value' => $query['value']],
            )),
            '/metrics/hit' => $this->hit(),
            '/log' => $this->log($query['message']),
            default => $this->text('Not found', 404),
        };
    }

    private function cache(): CacheInterface
    {
        return $this->container->get(CacheStorageProviderInterface::class)->storage();
    }

    private function hit(): ResponseInterface
    {
        $this->container->get(MetricsInterface::class)->add('acceptance_hits', 1);

        return $this->text('ok');
    }

    private function log(string $message): ResponseInterface
    {
        $this->container->get(LoggerInterface::class)->warning($message);

        return $this->text('ok');
    }

    private function text(string $body, int $status = 200): ResponseInterface
    {
        $response = $this->responses->createResponse($status);
        $response->getBody()->write($body);

        return $response;
    }
}
