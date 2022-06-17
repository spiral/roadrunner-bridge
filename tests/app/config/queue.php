<?php

declare(strict_types=1);

use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;

return [
    /**
     *  Default queue connection name
     */
    'default' => env('QUEUE_CONNECTION', 'sync'),

    /**
     *  Aliases for queue connections, if you want to use domain specific queues
     */
    'aliases' => [
        'mail-queue' => 'roadrunner',
        'rating-queue' => 'sync',
    ],

    /**
     * Queue connections
     * Drivers: "sync", "roadrunner"
     */
    'connections' => [
        'sync' => [
            // Job will be handled immediately without queueing
            'driver' => 'sync',
        ],
        'laravel' => [
            'driver' => 'laravel',
        ],
        'roadrunner' => [
            'driver' => 'roadrunner',
            'default' => 'memory', // Required
            'aliases' => [ // Optional
                'foo' => 'memory',
            ],
            'pipelines' => [
                'memory' => [
                    'connector' => new MemoryCreateInfo('local'), // Required
                    // Run consumer for this pipeline on startup (by default)
                    // You can pause consumer for this pipeline via console command
                    // php app.php queue:pause local
                    'consume' => true, // Optional
                ],
                'withSerializer' => [
                    'connector' => new MemoryCreateInfo('local'),
                    'serializerFormat' => 'serializer',
                    'consume' => true,
                ]
                // 'amqp' => [
                //     'connector' => new AMQPCreateInfo('bus', ...),
                //     // Don't consume jobs for this pipeline on start
                //     // You can run consumer for this pipeline via console command
                //     // php app.php queue:resume local
                //     'consume' => false
                // ],
                //
                // 'beanstalk' => [
                //     'connector' => new BeanstalkCreateInfo('bus', ...),
                // ],
                //
                // 'sqs' => [
                //     'connector' => new SQSCreateInfo('amazon', ...),
                // ],
            ],
        ],
    ],

    'driverAliases' => [
        'sync' => \Spiral\Queue\Driver\SyncDriver::class,
        'roadrunner' => \Spiral\RoadRunnerBridge\Queue\Queue::class,
    ],

    'registry' => [
        'handlers' => [],
    ],
];
