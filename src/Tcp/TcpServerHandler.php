<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp;

use Spiral\Core\CoreInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\LocatorInterface;

class TcpServerHandler implements CoreInterface
{
    private LocatorInterface $locator;

    public function __construct(LocatorInterface $locator)
    {
        $this->locator = $locator;
    }

    public function callAction(string $controller, string $action, array $parameters = [])
    {
        return $this->locator->getService($controller)->handle($parameters['request']);
    }
}
