<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpResponse;

class CloseConnection implements ResponseInterface
{
    public function getAction(): TcpResponse
    {
        return TcpResponse::Close;
    }

    public function getBody(): string
    {
        return '';
    }
}
