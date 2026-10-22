<?php

declare(strict_types=1);

use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\RoadRunnerBridge\Queue\Internal\Queue;

return [
    'default' => 'roadrunner',
    'connections' => [
        'roadrunner' => [
            'driver' => 'roadrunner',
            'default' => 'memory',
        ],
    ],
    'pipelines' => [
        // Absent from .rr.yaml: the bridge declares the pipeline in RoadRunner when a jobs worker boots.
        'memory' => [
            'connector' => new MemoryCreateInfo('acceptance'),
            'consume' => true,
        ],
    ],
    'driverAliases' => [
        'roadrunner' => Queue::class,
    ],
];
