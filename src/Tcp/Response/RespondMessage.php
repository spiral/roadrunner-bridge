<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpWorkerInterface;

class RespondMessage implements ResponseInterface
{
    public function __construct(
        private readonly string $body,
        private readonly bool $close = false
    ) {
    }

    public function getAction(): string
    {
        if ($this->close) {
            return TcpWorkerInterface::TCP_RESPOND_CLOSE;
        }

        return TcpWorkerInterface::TCP_RESPOND;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
