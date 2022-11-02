<?php

declare(strict_types=1);

namespace Spiral\Tests\Logger;

use Psr\Log\LoggerInterface;
use RoadRunner\Logger\Logger;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunnerBridge\Logger\Handler;
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
                "%message% foo"
            ),
        ]);

        $this->getContainer()->bind(LoggerInterface::class, $monolog);

        $logger = $this->getContainer()->get(LoggerInterface::class);

        $rpc->shouldReceive('call')
            ->once()
            ->with($expectedResult['level'], $expectedResult['message'])
            ->andReturnSelf();

        $method = $input['method'];

        $logger->$method($input['message']);
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
                ],
            ],
        ];
    }
}