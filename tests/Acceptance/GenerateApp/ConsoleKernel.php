<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\GenerateApp;

use Spiral\Console\Bootloader\ConsoleBootloader;
use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;
use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;

final class ConsoleKernel extends \Spiral\Framework\Kernel
{
    protected const LOAD = [
        TokenizerListenerBootloader::class,
        ConsoleBootloader::class,
        RoadRunnerBridge\RoadRunnerBootloader::class,
        RoadRunnerBridge\GRPCBootloader::class,
        RoadRunnerBridge\CommandBootloader::class,
    ];
}
