<?php

declare(strict_types=1);

namespace Spiral\App\Centrifugo;

use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;

class TestService implements ServiceInterface
{
    public function handle(RequestInterface $request): void
    {
    }
}
