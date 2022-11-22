<?php

declare(strict_types=1);

namespace Spiral\Tests\Logger;

use RoadRunner\Logger\Logger;
use Spiral\Goridge\RPC\RPCInterface;
use Monolog\Handler\HandlerInterface;
use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\Tests\TestCase;
use Mockery as m;
use Monolog\Logger as Monolog;

final class HandlerTest extends TestCase
{
    public function testLoggerShouldSendDataToRRIfFallbackNull(): void
    {
        $rpc = m::mock(RPCInterface::class);

        $rpc->shouldReceive('withServicePrefix')->once()->with('app')->andReturnSelf();

        $monolog = new Monolog('default');

        $monolog->setHandlers([
            new Handler(
                new Logger($rpc),
                null,
                '%message% foo'
            ),
        ]);

        $rpc->shouldReceive('call')
            ->once()
            ->with('Error', 'Error message foo')
            ->andReturnSelf();

        $monolog->error("Error message");
    }

    public function testLoggerShouldSendDataToFallback(): void
    {
        $rpc = m::mock(RPCInterface::class);

        $rpc->shouldReceive('withServicePrefix')->once()->with('app')->andReturnSelf();

        $monolog = new Monolog('default');

        $monolog->setHandlers([
            new Handler(
                new Logger($rpc),
                $fallback = m::mock(HandlerInterface::class),
                '%message% foo'
            ),
        ]);

        $fallback->shouldReceive('handle')->withArgs(function (array $record) {
            return $record['message'] === 'Error message';
        })->andReturn(true);

        $monolog->error("Error message");
    }
}
