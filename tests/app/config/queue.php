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
        'roadrunner' => [
            'driver' => 'roadrunner',
            // Pipeline jobs are pushed to unless Options::onQueue() names another one
            'default' => 'memory',
        ],
    ],

    /**
     * RoadRunner pipelines the bridge declares and connects to
     */
    'pipelines' => [
        'memory' => [
            'connector' => new MemoryCreateInfo('local'), // Required
            // Run consumer for this pipeline on startup (by default)
            // You can pause consumer for this pipeline via console command
            // php app.php rr:jobs:pause local
            'consume' => true, // Optional
        ],
        // 'amqp' => [
        //     'connector' => new AMQPCreateInfo('bus', ...),
        //     // Don't consume jobs for this pipeline on start
        //     // You can run consumer for this pipeline via console command
        //     // php app.php rr:jobs:consume amqp
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

    'registry' => [
        'handlers' => [],
        'serializers' => [
            \Spiral\Queue\Job\ObjectJob::class => 'json',
            \Spiral\App\Job\TestJob::class => new \Spiral\Serializer\Serializer\PhpSerializer(),
        ],
    ],
];
