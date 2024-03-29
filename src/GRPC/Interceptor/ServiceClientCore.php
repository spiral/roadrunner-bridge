<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Interceptor;

use Spiral\Core\CoreInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;

final class ServiceClientCore extends \Grpc\BaseStub implements CoreInterface
{
    public function callAction(string $controller, string $action, array $parameters = []): array
    {
        \assert($parameters['ctx'] instanceof ContextInterface);

        /** @var ContextInterface $ctx */
        $ctx = $parameters['ctx'];

        return $this->_simpleRequest(
            $action,
            $parameters['in'],
            [$parameters['responseClass'], 'decode'],
            (array) $ctx->getValue('metadata'),
            (array) $ctx->getValue('options'),
        )->wait();
    }
}
