<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpResponse;

class RespondMessage implements ResponseInterface
{
    public function __construct(
        private readonly string $body,
        private readonly bool $close = false,
    ) {
    }

    public function getAction(): TcpResponse
    {
        if ($this->close) {
            return TcpResponse::RespondClose;
        }

        return TcpResponse::Respond;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
