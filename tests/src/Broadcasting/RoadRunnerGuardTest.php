<?php

declare(strict_types=1);

namespace Spiral\Tests\Broadcasting;

use Mockery as m;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Broadcasting\TopicRegistryInterface;
use Spiral\Core\Container;
use Spiral\Core\InvokerInterface;
use Spiral\Core\ScopeInterface;
use Spiral\Http\Config\HttpConfig;
use Spiral\Http\Diactoros\ResponseFactory;
use Spiral\RoadRunnerBridge\Broadcasting\RoadRunnerGuard;
use Spiral\Tests\TestCase;

final class RoadRunnerGuardTest extends TestCase
{
    private ResponseFactoryInterface $responseFactory;
    private InvokerInterface $invoker;
    private ScopeInterface $scope;
    /**§ @var m\LegacyMockInterface|m\MockInterface|TopicRegistryInterface */
    private $topics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->responseFactory = new ResponseFactory(
            new HttpConfig([
                'headers' => [],
            ])
        );

        $this->scope = $this->invoker = new Container();
        $this->topics = m::mock(TopicRegistryInterface::class);
    }

    public function testRequestShouldBeAuthorizedWhenRequestDoesNotContainAttributes(): void
    {
        $request = m::mock(ServerRequestInterface::class);

        $request->shouldReceive('getAttribute')->with('ws:joinServer')->andReturnNull();
        $request->shouldReceive('getAttribute')->with('ws:joinTopics')->andReturnNull();

        $response = $this->makeGuard()->authorize($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRequestShouldBeAuthorizedWhenRequestContainJoinServerAttributesWithoutCallback(): void
    {
        $request = m::mock(ServerRequestInterface::class);

        $request->shouldReceive('getAttribute')->with('ws:joinServer')->andReturnTrue();

        $response = $this->makeGuard()->authorize($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRequestShouldNotBeAuthorizedWhenRequestContainJoinServerAttributesButCallbackReturnFalse(): void
    {
        $request = m::mock(ServerRequestInterface::class);

        $request->shouldReceive('getAttribute')->with('ws:joinServer')->andReturnTrue();

        $response = $this->makeGuard(function () {
            return false;
        })->authorize($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testRequestShouldBeAuthorizedWhenRequestContainJoinTopicsAttributesButCallbackReturnTrue(): void
    {
        $request = m::mock(ServerRequestInterface::class);

        $request->shouldReceive('getAttribute')->with('ws:joinServer')->andReturnNull();
        $request->shouldReceive('getAttribute')->with('ws:joinTopics')->andReturn('topic_name');

        $this->topics->shouldReceive('findCallback')->with('topic_name', [])->andReturn(function () {
            return true;
        });
        $response = $this->makeGuard()->authorize($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRequestShouldNotBeAuthorizedWhenRequestContainJoinTopicsAttributesButOneOfCallbacksReturnFalse(): void
    {
        $request = m::mock(ServerRequestInterface::class);

        $request->shouldReceive('getAttribute')->with('ws:joinServer')->andReturnNull();
        $request->shouldReceive('getAttribute')->with('ws:joinTopics')->andReturn('topic_name,topic_name2');

        $this->topics->shouldReceive('findCallback')->with('topic_name', [])->andReturn(function () {
            return true;
        });

        $this->topics->shouldReceive('findCallback')->with('topic_name2', [])->andReturn(function () {
            return false;
        });

        $response = $this->makeGuard()->authorize($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    private function makeGuard(?callable $serverCallback = null): RoadRunnerGuard
    {
        return new RoadRunnerGuard(
            $this->responseFactory,
            $this->invoker,
            $this->scope,
            $this->topics,
            $serverCallback
        );
    }
}