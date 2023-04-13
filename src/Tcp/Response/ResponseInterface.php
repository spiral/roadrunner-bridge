<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Response;

use Spiral\RoadRunner\Tcp\TcpResponse;

interface ResponseInterface
{
    public function getBody(): string;

    public function getAction(): TcpResponse;
}
