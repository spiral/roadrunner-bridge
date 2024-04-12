<?php

declare(strict_types=1);

namespace Spiral\App\Tcp;

use Spiral\RoadRunner\Tcp\Request;
use Spiral\RoadRunnerBridge\Tcp\Response\RespondMessage;
use Spiral\RoadRunnerBridge\Tcp\Response\ResponseInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;

class TestService implements ServiceInterface
{
    public function handle(Request $request): ResponseInterface
    {
        return new RespondMessage($request->getBody(), true);
    }
}
