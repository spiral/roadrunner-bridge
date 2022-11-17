<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo;

use RoadRunner\Centrifugo\Request\RequestInterface;
use RoadRunner\Centrifugo\Request\RequestType;
use Spiral\Core\CoreInterface;

final class RequestHandler implements CoreInterface
{
    public function __construct(
        private readonly RegistryInterface $registry
    ) {
    }

    public function callAction(string $controller, string $action, array $parameters = []): mixed
    {
        \assert($parameters['request'] instanceof RequestInterface);
        \assert($parameters['type'] instanceof RequestType);

        $service = $this->registry->getService($parameters['type']);

        $service->handle($parameters['request']);

        return true;
    }
}
