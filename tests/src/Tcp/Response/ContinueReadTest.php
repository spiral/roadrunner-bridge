<?php

declare(strict_types=1);

namespace Spiral\Tests\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpWorkerInterface;
use Spiral\RoadRunnerBridge\Tcp\Response\ContinueRead;
use Spiral\Tests\TestCase;

final class ContinueReadTest extends TestCase
{
    public function testContinueRead(): void
    {
        $response = new ContinueRead();

        $this->assertSame(TcpWorkerInterface::TCP_READ, $response->getAction());
        $this->assertSame('', $response->getBody());
    }
}
