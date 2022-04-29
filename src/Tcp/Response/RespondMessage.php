<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpWorkerInterface;

class RespondMessage implements ResponseInterface
{
    private string $body;
    private bool $close;

    public function __construct(string $body, bool $close = false)
    {
        $this->body = $body;
        $this->close = $close;
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
