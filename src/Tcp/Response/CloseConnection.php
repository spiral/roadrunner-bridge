<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpWorkerInterface;

class CloseConnection implements ResponseInterface
{
    public function getAction(): string
    {
        return TcpWorkerInterface::TCP_CLOSE;
    }

    public function getBody(): string
    {
        return '';
    }
}
