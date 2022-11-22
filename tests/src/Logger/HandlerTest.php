<?php

declare(strict_types=1);

namespace Spiral\Tests\Logger;

use RoadRunner\Logger\Logger;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\RoadRunnerBridge\RoadRunnerMode;
use Spiral\Tests\TestCase;
use Mockery as m;
use Monolog\Logger as Monolog;

final class HandlerTest extends TestCase
{
    /**
     * @dataProvider provideLogData
     */
    public function testSendLog($expectedResult, $input): void
    {
        $rpc = m::mock(RPCInterface::class);

        $rpc->shouldReceive('withServicePrefix')->once()->with('app')->andReturnSelf();

        $monolog = new Monolog('default');

        $monolog->setHandlers([
            new Handler(
                new Logger($rpc),
                $input['mode'],
                '%message% foo'
            ),
        ]);

        if ($input['mode'] !== RoadRunnerMode::Unknown) {
            $rpc->shouldReceive('call')
                ->once()
                ->with($expectedResult['level'], $expectedResult['message'])
                ->andReturnSelf();
        }

        $method = $input['method'];

        $monolog->$method($input['message']);
    }

    public function provideLogData(): array
    {
        return [
            [
                [
                    'level' => 'Error',
                    'message' => 'Error message foo',
                ],
                [
                    'method' => 'error',
                    'message' => 'Error message',
                    'mode' => RoadRunnerMode::Http,
                ],
            ],
            [
                [
                    'level' => 'Warning',
                    'message' => 'Warning message foo',
                ],
                [
                    'method' => 'warning',
                    'message' => 'Warning message',
                    'mode' => RoadRunnerMode::Temporal,
                ],
            ],
            [
                [
                    'level' => 'Info',
                    'message' => 'Info message foo',
                ],
                [
                    'method' => 'info',
                    'message' => 'Info message',
                    'mode' => RoadRunnerMode::Jobs,
                ],
            ],
            [
                [
                    'level' => 'Debug',
                    'message' => 'Debug message foo',
                ],
                [
                    'method' => 'debug',
                    'message' => 'Debug message',
                    'mode' => RoadRunnerMode::Grpc,
                ],
            ],
            [
                [
                    'level' => 'Warning',
                    'message' => 'Emergency message foo',
                ],
                [
                    'method' => 'emergency',
                    'message' => 'Emergency message',
                    'mode' => RoadRunnerMode::Tcp,
                ],
            ],
            [
                [
                    'level' => 'Warning',
                    'message' => 'Alert message foo',
                ],
                [
                    'method' => 'alert',
                    'message' => 'Alert message',
                    'mode' => RoadRunnerMode::Centrifuge,
                ],
            ],
            [
                [
                    'level' => 'Error',
                    'message' => 'Critical message foo',
                ],
                [
                    'method' => 'critical',
                    'message' => 'Critical message',
                    'mode' => RoadRunnerMode::Unknown,
                ],
            ],
            [
                [
                    'level' => 'Info',
                    'message' => 'Notice message foo',
                ],
                [
                    'method' => 'notice',
                    'message' => 'Notice message',
                    'mode' => RoadRunnerMode::Http,
                ],
            ],
        ];
    }
}
