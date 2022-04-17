<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Response;

interface ResponseInterface
{
    public function getBody(): string;

    public function getAction(): string;
}
