<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpResponse;

class ContinueRead implements ResponseInterface
{
    public function getAction(): TcpResponse
    {
        return TcpResponse::Read;
    }

    public function getBody(): string
    {
        return '';
    }
}
