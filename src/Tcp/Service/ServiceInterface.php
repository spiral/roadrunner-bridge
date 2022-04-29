<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Service;

use Spiral\RoadRunner\Tcp\Request;
use Spiral\RoadRunnerBridge\Tcp\Response\ResponseInterface;

interface ServiceInterface
{
    public function handle(Request $request): ResponseInterface;
}
