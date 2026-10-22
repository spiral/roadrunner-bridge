<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\App;

use Spiral\Bootloader as Framework;
use Spiral\Cache\Bootloader\CacheBootloader;
use Spiral\Monolog\Bootloader\MonologBootloader;
use Spiral\Nyholm\Bootloader\NyholmBootloader;
use Spiral\Queue\Bootloader\QueueBootloader;
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;
use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;

/**
 * The application every RoadRunner worker pool runs: `RR_MODE` picks the bridge dispatcher.
 */
final class Kernel extends \Spiral\Framework\Kernel
{
    protected const LOAD = [
        TokenizerListenerBootloader::class,
        NyholmBootloader::class,
        Framework\Http\HttpBootloader::class,
        CacheBootloader::class,
        QueueBootloader::class,
        MonologBootloader::class,

        RoadRunnerBridge\RoadRunnerBootloader::class,
        RoadRunnerBridge\HttpBootloader::class,
        RoadRunnerBridge\QueueBootloader::class,
        RoadRunnerBridge\CacheBootloader::class,
        RoadRunnerBridge\GRPCBootloader::class,
        RoadRunnerBridge\MetricsBootloader::class,
        RoadRunnerBridge\LoggerBootloader::class,

        AppBootloader::class,
    ];
}
