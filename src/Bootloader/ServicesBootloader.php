<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\RoadRunner\Services\Manager;

final class ServicesBootloader extends Bootloader
{
    public function defineDependencies(): array
    {
        return [
            RoadRunnerBootloader::class,
        ];
    }

    public function defineSingletons(): array
    {
        return [
            Manager::class => Manager::class,
        ];
    }
}
