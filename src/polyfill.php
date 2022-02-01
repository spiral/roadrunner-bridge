<?php

declare(strict_types=1);

class_alias(
    \Spiral\RoadRunnerBridge\Bootloader\RoadRunnerBootloader::class,
    \Spiral\Bootloader\ServerBootloader::class
);

class_alias(
    \Spiral\RoadRunnerBridge\Bootloader\GRPCBootloader::class,
    \Spiral\Bootloader\GRPC\GRPCBootloader::class
);
