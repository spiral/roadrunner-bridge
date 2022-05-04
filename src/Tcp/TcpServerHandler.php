<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp;

use Spiral\Core\CoreInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\RegistryInterface;

class TcpServerHandler implements CoreInterface
{
    public function __construct(
        private RegistryInterface $registry
    ) {
    }

    public function callAction(string $controller, string $action, array $parameters = []): mixed
    {
        return $this->registry->getService($controller)->handle($parameters['request']);
    }
}
