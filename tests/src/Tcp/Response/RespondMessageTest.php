<?php

declare(strict_types=1);

namespace Spiral\Tests\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpWorkerInterface;
use Spiral\RoadRunnerBridge\Tcp\Response\RespondMessage;
use Spiral\Tests\TestCase;

final class RespondMessageTest extends TestCase
{
    public function testRespondMessage(): void
    {
        $response = new RespondMessage('test');

        $this->assertSame(TcpWorkerInterface::TCP_RESPOND, $response->getAction());
        $this->assertSame('test', $response->getBody());
    }

    public function testRespondMessageAndClose(): void
    {
        $response = new RespondMessage('test', true);

        $this->assertSame(TcpWorkerInterface::TCP_RESPOND_CLOSE, $response->getAction());
        $this->assertSame('test', $response->getBody());
    }
}
