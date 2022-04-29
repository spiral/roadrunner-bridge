<?php

declare(strict_types=1);

namespace Spiral\App\Tcp;

use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\CoreInterface;
use Spiral\RoadRunnerBridge\Tcp\Response\ContinueRead;

class TestInterceptor implements CoreInterceptorInterface
{
    private array $data = [];

    public function process(string $controller, string $action, array $parameters, CoreInterface $core)
    {
        if (\count($this->data) < 5) {
            $this->data[] = $parameters['request']->body;
        }

        if (\count($this->data) === 5) {
            $parameters['request']->body = \json_encode($this->data, JSON_THROW_ON_ERROR);

            return $core->callAction($controller, $action, $parameters);
        }

        return new ContinueRead();
    }
}
