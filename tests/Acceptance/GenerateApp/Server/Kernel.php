<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\GenerateApp\Server;

use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;
use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;

/**
 * Serves the services implemented in `Service/` against the code `grpc:generate` produced.
 */
final class Kernel extends \Spiral\Framework\Kernel
{
    protected const LOAD = [
        TokenizerListenerBootloader::class,
        RoadRunnerBridge\RoadRunnerBootloader::class,
        RoadRunnerBridge\GRPCBootloader::class,
    ];
}
