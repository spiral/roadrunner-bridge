<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp;

use Spiral\Core\CoreInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\RegistryInterface;

class TcpServerHandler implements CoreInterface
{
    private RegistryInterface $registry;

    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    public function callAction(string $controller, string $action, array $parameters = [])
    {
        return $this->registry->getService($controller)->handle($parameters['request']);
    }
}
