<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use RoadRunner\Lock\Lock;
use RoadRunner\Lock\LockInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class LockBootloader extends Bootloader
{
    public function defineBindings(): array
    {
        return [
            RoadRunnerBootloader::class,
        ];
    }

    public function defineSingletons(): array
    {
        return [
            LockInterface::class => Lock::class,
        ];
    }
}
