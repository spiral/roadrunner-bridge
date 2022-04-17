<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config\Exception\Tcp;

final class ServiceNotFoundException extends \RuntimeException
{
    /**
     * @psalm-param non-empty-string $server
     */
    public function __construct(string $server)
    {
        parent::__construct(\sprintf('Service for server [%s] not found.', $server));
    }
}