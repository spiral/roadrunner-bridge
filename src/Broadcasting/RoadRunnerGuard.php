<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Broadcasting;

use Psr\Http\Message\ServerRequestInterface;
use Spiral\Broadcasting\AuthorizationStatus;
use Spiral\Broadcasting\GuardInterface;
use Spiral\Broadcasting\TopicRegistryInterface;
use Spiral\Core\InvokerInterface;
use Spiral\Core\ScopeInterface;

final class RoadRunnerGuard implements GuardInterface
{
    /** @var callable|null */
    private mixed $serverAuthorizeCallback;

    public function __construct(
        private readonly InvokerInterface $invoker,
        private readonly ScopeInterface $scope,
        private readonly TopicRegistryInterface $topics,
        ?callable $serverAuthorizeCallback = null
    ) {
        $this->serverAuthorizeCallback = $serverAuthorizeCallback;
    }

    public function authorize(
        ServerRequestInterface $request
    ): AuthorizationStatus {
        // server authorization
        if ($request->getAttribute('ws:joinServer') !== null) {
            if (!$this->authorizeServer($request)) {
                return new AuthorizationStatus(false, []);
            }

            return new AuthorizationStatus(true, []);
        }

        // topic authorization
        $topics = $request->getAttribute('ws:joinTopics');
        if (\is_string($topics)) {
            $topics = \explode(',', $topics);
            foreach ($topics as $topic) {
                if (!$this->authorizeTopic($request, $topic)) {
                    return new AuthorizationStatus(false, [$topic]);
                }
            }

            return new AuthorizationStatus(true, $topics);
        }

        return new AuthorizationStatus(true, []);
    }

    private function authorizeServer(ServerRequestInterface $request): bool
    {
        if ($this->serverAuthorizeCallback === null) {
            return true;
        }

        return $this->invoke($request, $this->serverAuthorizeCallback);
    }

    private function authorizeTopic(ServerRequestInterface $request, string $topic): bool
    {
        $parameters = [];
        $callback = $this->topics->findCallback($topic, $parameters);
        if ($callback === null) {
            return false;
        }

        return $this->invoke($request, $callback, $parameters + ['topic' => $topic]);
    }

    private function invoke(ServerRequestInterface $request, callable $callback, array $parameters = []): bool
    {
        return $this->scope->runScope(
            [
                ServerRequestInterface::class => $request,
            ],
            fn (): bool => $this->invoker->invoke($callback, $parameters)
        );
    }
}
