<?php

declare(strict_types=1);

namespace Spiral\App\Tcp;

use Spiral\RoadRunner\Tcp\Request;
use Spiral\RoadRunnerBridge\Tcp\Response\ResponseInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;

class ServiceWithException implements ServiceInterface
{
    public function handle(Request $request): ResponseInterface
    {
        throw new \RuntimeException('some error');
    }
}
