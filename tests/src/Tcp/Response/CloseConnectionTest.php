<?php

declare(strict_types=1);

namespace Spiral\Tests\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpWorkerInterface;
use Spiral\RoadRunnerBridge\Tcp\Response\CloseConnection;
use Spiral\Tests\TestCase;

final class CloseConnectionTest extends TestCase
{
    public function testCloseConnection(): void
    {
        $response = new CloseConnection();

        $this->assertSame(TcpWorkerInterface::TCP_CLOSE, $response->getAction());
        $this->assertSame('', $response->getBody());
    }
}
