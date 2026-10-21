<?php

declare(strict_types=1);

return [
    'default' => 'roadrunner',
    'storages' => [
        'roadrunner' => [
            'type' => 'roadrunner',
            // The `kv.local` storage of .rr.yaml.
            'driver' => 'local',
        ],
    ],
];
