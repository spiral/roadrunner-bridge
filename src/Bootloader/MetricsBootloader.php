<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Metrics\Metrics;
use Spiral\RoadRunner\Metrics\MetricsInterface;

final class MetricsBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        RoadRunnerBootloader::class,
    ];

    protected const SINGLETONS = [
        MetricsInterface::class => [self::class, 'initMetrics'],
    ];

    protected function initMetrics(RPCInterface $rpc): MetricsInterface
    {
        return new Metrics($rpc);
    }
}
