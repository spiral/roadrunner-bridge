<?php

declare(strict_types=1);

namespace Spiral\Tests\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpResponse;
use Spiral\RoadRunnerBridge\Tcp\Response\RespondMessage;
use Spiral\Tests\TestCase;

final class RespondMessageTest extends TestCase
{
    public function testRespondMessage(): void
    {
        $response = new RespondMessage('test');

        $this->assertSame(TcpResponse::Respond, $response->getAction());
        $this->assertSame('test', $response->getBody());
    }

    public function testRespondMessageAndClose(): void
    {
        $response = new RespondMessage('test', true);

        $this->assertSame(TcpResponse::RespondClose, $response->getAction());
        $this->assertSame('test', $response->getBody());
    }
}
