<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Service;

interface LocatorInterface
{
    public function getService(string $server): ServiceInterface;
}
