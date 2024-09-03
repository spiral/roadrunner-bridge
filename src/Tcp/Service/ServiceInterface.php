<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Service;

use Spiral\RoadRunner\Tcp\Request;
use Spiral\RoadRunner\Tcp\RequestInterface;
use Spiral\RoadRunnerBridge\Tcp\Response\ResponseInterface;

interface ServiceInterface
{
    /**
     * @param Request $request Will be changed to {@see RequestInterface} in the v4.0.
     */
    public function handle(Request $request): ResponseInterface;
}
