<?php

declare(strict_types=1);

namespace Spiral\Tests\Broadcasting;

use Mockery as m;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Broadcasting\AuthorizationStatus;
use Spiral\Broadcasting\GuardInterface;
use Spiral\RoadRunner\Broadcast\BroadcastInterface;
use Spiral\RoadRunnerBridge\Broadcasting\RoadRunnerBroadcast;
use Spiral\Tests\TestCase;

final class RoadRunnerBroadcastTest extends TestCase
{
    private RoadRunnerBroadcast $broadcast;
    /** @var BroadcastInterface|m\LegacyMockInterface|m\MockInterface */
    private $baseBroadcast;
    /** @var GuardInterface|m\LegacyMockInterface|m\MockInterface */
    private $guard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->broadcast = new RoadRunnerBroadcast(
            $this->baseBroadcast = m::mock(BroadcastInterface::class),
            $this->guard = m::mock(GuardInterface::class)
        );
    }

    public function testPublish(): void
    {
        $this->baseBroadcast->shouldReceive('publish')->with('foo', 'bar')->once();

        $this->broadcast->publish('foo', 'bar');
    }

    public function testJoin(): void
    {
        $this->baseBroadcast->shouldReceive('join')->with('foo')->once();

        $this->broadcast->join('foo');
    }

    public function testAuthorize(): void
    {
        $request = m::mock(ServerRequestInterface::class);

        $this->guard->shouldReceive('authorize')
            ->with($request)
            ->once()
            ->andReturn($status = new AuthorizationStatus(true, ['foo']));

        $this->assertSame($status, $this->broadcast->authorize($request));
    }
}
