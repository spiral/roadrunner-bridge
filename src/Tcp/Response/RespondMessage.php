<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpResponse;

final readonly class RespondMessage implements ResponseInterface
{
    public function __construct(
        private string $body,
        private bool $close = false,
    ) {}

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
