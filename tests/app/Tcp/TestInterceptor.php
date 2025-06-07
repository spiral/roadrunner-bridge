<?php

declare(strict_types=1);

namespace Spiral\App\Tcp;

use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\CoreInterface;
use Spiral\RoadRunner\Tcp\Request;
use Spiral\RoadRunnerBridge\Tcp\Response\ContinueRead;

class TestInterceptor implements CoreInterceptorInterface
{
    private array $data = [];

    public function process(string $controller, string $action, array $parameters, CoreInterface $core): mixed
    {
        $request = $parameters[0];
        if (\count($this->data) < 5) {
            $this->data[] = $request->getBody();
        }

        if (\count($this->data) === 5) {
            $parameters[0] = new Request(
                remoteAddr: $request->getRemoteAddress(),
                event: $request->getEvent(),
                body: \json_encode($this->data, JSON_THROW_ON_ERROR),
                connectionUuid: $request->getConnectionUuid(),
                server: $request->getServer(),
            );

            return $core->callAction($controller, $action, $parameters);
        }

        return new ContinueRead();
    }
}
