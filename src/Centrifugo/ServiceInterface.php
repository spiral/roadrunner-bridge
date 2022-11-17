<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo;

use RoadRunner\Centrifugo\Request\RequestInterface;

interface ServiceInterface
{
    public function handle(RequestInterface $request): void;
}
