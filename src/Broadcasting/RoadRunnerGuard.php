<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Broadcasting;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Broadcasting\GuardInterface;
use Spiral\Broadcasting\TopicRegistryInterface;
use Spiral\Core\InvokerInterface;
use Spiral\Core\ScopeInterface;

final class RoadRunnerGuard implements GuardInterface
{
    private TopicRegistryInterface $topics;
    private InvokerInterface $invoker;
    private ScopeInterface $scope;
    private ResponseFactoryInterface $responseFactory;
    /** @var callable|null */
    private $serverAuthorizeCallback;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        InvokerInterface $invoker,
        ScopeInterface $scope,
        TopicRegistryInterface $topics,
        ?callable $serverAuthorizeCallback = null
   ) {
        $this->responseFactory = $responseFactory;
        $this->invoker = $invoker;
        $this->scope = $scope;
        $this->topics = $topics;
        $this->serverAuthorizeCallback = $serverAuthorizeCallback;
    }

    public function authorize(
        ServerRequestInterface $request
    ): ResponseInterface {
        // server authorization
        if ($request->getAttribute('ws:joinServer') !== null) {
            if (!$this->authorizeServer($request)) {
                return $this->responseFactory->createResponse(403);
            }

            return $this->responseFactory->createResponse(200);
        }

        // topic authorization
        $topics = $request->getAttribute('ws:joinTopics');
        if (\is_string($topics)) {
            foreach (\explode(',', $topics) as $topic) {
                if (!$this->authorizeTopic($request, $topic)) {
                    return $this->responseFactory->createResponse(403);
                }
            }
        }

        return $this->responseFactory->createResponse(200);
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
            fn(): bool => $this->invoker->invoke($callback, $parameters)
        );
    }
}