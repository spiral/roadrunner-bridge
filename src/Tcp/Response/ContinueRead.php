<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpWorkerInterface;

class ContinueRead implements ResponseInterface
{
    public function getAction(): string
    {
        return TcpWorkerInterface::TCP_READ;
    }

    public function getBody(): string
    {
        return '';
    }
}
