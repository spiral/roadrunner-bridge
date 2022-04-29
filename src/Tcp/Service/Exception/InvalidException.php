<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp\Service\Exception;

use Spiral\Core\Container\Autowire;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;

final class InvalidException extends \RuntimeException
{
    /**
     * @psalm-param non-empty-string $type
     */
    public function __construct(string $type)
    {
        parent::__construct(
            \sprintf('Service must be type of %s|%s|string, %s given.', ServiceInterface::class, Autowire::class, $type)
        );
    }
}
