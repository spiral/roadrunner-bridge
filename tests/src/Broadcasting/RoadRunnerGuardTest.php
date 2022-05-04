<?php

declare(strict_types=1);

namespace Spiral\Tests\Broadcasting;

use Mockery as m;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Broadcasting\TopicRegistryInterface;
use Spiral\Core\Container;
use Spiral\Core\InvokerInterface;
use Spiral\Core\ScopeInterface;
use Spiral\RoadRunnerBridge\Broadcasting\RoadRunnerGuard;
use Spiral\Tests\TestCase;

final class RoadRunnerGuardTest extends TestCase
{
    private InvokerInterface $invoker;
    private ScopeInterface $scope;
    /**§ @var m\LegacyMockInterface|m\MockInterface|TopicRegistryInterface */
    private $topics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scope = $this->invoker = new Container();
        $this->topics = m::mock(TopicRegistryInterface::class);
    }

    public function testRequestShouldBeAuthorizedWhenRequestDoesNotContainAttributes(): void
    {
        $request = m::mock(ServerRequestInterface::class);

        $request->shouldReceive('getAttribute')->with('ws:joinServer')->andReturnNull();
        $request->shouldReceive('getAttribute')->with('ws:joinTopics')->andReturnNull();

        $status = $this->makeGuard()->authorize($request);
        $this->assertTrue($status->success);
    }

    public function testRequestShouldBeAuthorizedWhenRequestContainJoinServerAttributesWithoutCallback(): void
    {
        $request = m::mock(ServerRequestInterface::class);

        $request->shouldReceive('getAttribute')->with('ws:joinServer')->andReturnTrue();

        $status = $this->makeGuard()->authorize($request);
        $this->assertTrue($status->success);
    }

    public function testRequestShouldNotBeAuthorizedWhenRequestContainJoinServerAttributesButCallbackReturnFalse(): void
    {
        $request = m::mock(ServerRequestInterface::class);

        $request->shouldReceive('getAttribute')->with('ws:joinServer')->andReturnTrue();

        $status = $this->makeGuard(function () {
            return false;
        })->authorize($request);

        $this->assertFalse($status->success);
    }

    public function testRequestShouldBeAuthorizedWhenRequestContainJoinTopicsAttributesButCallbackReturnTrue(): void
    {
        $request = m::mock(ServerRequestInterface::class);

        $request->shouldReceive('getAttribute')->with('ws:joinServer')->andReturnNull();
        $request->shouldReceive('getAttribute')->with('ws:joinTopics')->andReturn('topic_name,topic_name2');

        $this->topics->shouldReceive('findCallback')->with('topic_name', [])->andReturn(function () {
            return true;
        });
        $this->topics->shouldReceive('findCallback')->with('topic_name2', [])->andReturn(function () {
            return true;
        });
        $status = $this->makeGuard()->authorize($request);
        $this->assertTrue($status->success);
        $this->assertSame(['topic_name', 'topic_name2'], $status->topics);
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

        $status = $this->makeGuard()->authorize($request);

        $this->assertFalse($status->success);
        $this->assertSame(['topic_name2'], $status->topics);
    }

    private function makeGuard(?callable $serverCallback = null): RoadRunnerGuard
    {
        return new RoadRunnerGuard(
            $this->invoker,
            $this->scope,
            $this->topics,
            $serverCallback
        );
    }
}
