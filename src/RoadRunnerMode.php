<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge;

use Spiral\Boot\Injector\InjectableEnumInterface;
use Spiral\Boot\Injector\ProvideFrom;
use Spiral\RoadRunner\EnvironmentInterface;

#[ProvideFrom(method: 'detect')]
enum RoadRunnerMode: string implements InjectableEnumInterface
{
    case Unknown = 'unknown';
    case Http = 'http';
    case Temporal = 'temporal';
    case Jobs = 'jobs';
    case Grpc = 'grpc';
    case Tcp = 'tcp';

    public static function detect(EnvironmentInterface $environment): self
    {
        $value = $environment->getMode();

        return self::tryFrom($value) ?? self::Unknown;
    }
}
