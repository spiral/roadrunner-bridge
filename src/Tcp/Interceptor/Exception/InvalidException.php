<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Interceptor\Exception;

use Spiral\Core\Container\Autowire;
use Spiral\Core\CoreInterceptorInterface;

final class InvalidException extends \RuntimeException
{
    /**
     * @psalm-param non-empty-string $type
     */
    public function __construct(string $type)
    {
        parent::__construct(\sprintf(
            'Interceptor must be type of %s|%s|string, %s given.',
            CoreInterceptorInterface::class,
            Autowire::class,
            $type
        ));
    }
}
