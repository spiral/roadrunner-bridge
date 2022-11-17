<?php

declare(strict_types=1);

namespace Spiral\App\Centrifugo;

use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\CoreInterface;

class TestInterceptor implements CoreInterceptorInterface
{
    public function process(string $controller, string $action, array $parameters, CoreInterface $core): mixed
    {
        return '';
    }
}
