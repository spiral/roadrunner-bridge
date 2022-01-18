<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Boot\KernelInterface;
use Spiral\Bootloader\ServerBootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Core\Container;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\QueueInterface;
use Spiral\Queue\SerializerInterface;
use Spiral\Queue\SerializerRegistryInterface;
use Spiral\RoadRunner\Jobs\Consumer;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\RoadRunner\Jobs\QueueInterface as RRQueueInterface;
use Spiral\RoadRunner\Jobs\Serializer\SerializerInterface as RRSerializerInterface;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\Config\QueueConfig;
use Spiral\RoadRunnerBridge\Queue\ContainerRegistry;
use Spiral\RoadRunnerBridge\Queue\DefaultSerializer;
use Spiral\RoadRunnerBridge\Queue\Dispatcher;
use Spiral\RoadRunnerBridge\Queue\Failed\FailedJobHandlerInterface;
use Spiral\RoadRunnerBridge\Queue\Failed\LogFailedJobHandler;
use Spiral\RoadRunnerBridge\Queue\QueueManager;
use Spiral\RoadRunnerBridge\Queue\QueueRegistry;
use Spiral\RoadRunnerBridge\Queue\RoadRunnerSerializer;
use Spiral\SendIt\MailJob;
use Spiral\SendIt\MailQueue;

final class QueueBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        // RoadRunnerBootloader::class,
        ServerBootloader::class,
    ];

    protected const SINGLETONS = [
        HandlerRegistryInterface::class => QueueRegistry::class,
        SerializerRegistryInterface::class => QueueRegistry::class,
        FailedJobHandlerInterface::class => LogFailedJobHandler::class,
        QueueRegistry::class => [self::class, 'initRegistry'],
    ];

    private ConfiguratorInterface $config;

    public function __construct(
        ConfiguratorInterface $config
    ) {
        $this->config = $config;
    }

    public function boot(
        Container $container,
        EnvironmentInterface $env,
        KernelInterface $kernel,
        Dispatcher $jobs
    ): void {
        $this->initQueueConfig($env);

        $kernel->addDispatcher($jobs);

        $this->registerJobsSerializer($container);
        $this->registerJobs($container);
        $this->registerConsumer($container);
        $this->registerQueue($container);
    }

    private function registerJobsSerializer(Container $container): void
    {
        $container->bindSingleton(SerializerInterface::class, static function () {
            return new DefaultSerializer();
        });

        $container->bindSingleton(RRSerializerInterface::class, static function (SerializerInterface $serializer) {
            return new RoadRunnerSerializer($serializer);
        });
    }

    private function registerConsumer(Container $container): void
    {
        $container->bindSingleton(
            Consumer::class,
            static function (WorkerInterface $worker, RRSerializerInterface $serializer): Consumer {
                return new Consumer($worker, $serializer);
            }
        );

        $container->bindSingleton(
            ConsumerInterface::class,
            static function (Consumer $consumer): ConsumerInterface {
                return $consumer;
            }
        );
    }

    private function registerJobs(Container $container): void
    {
        $container->bindSingleton(
            JobsInterface::class,
            static function (RPCInterface $rpc, QueueConfig $config, RRSerializerInterface $serializer): Jobs {
                $jobs = new Jobs($rpc, $serializer);

                $queues = iterator_to_array($jobs->getIterator());

                foreach ($config->getConnections('roadrunner') as $connection) {
                    /** @var CreateInfoInterface $connector */
                    $connector = $connection['connector'];

                    if (! isset($queues[$connector->getName()])) {
                        $queue = $jobs->create($connector);
                        $shouldBeConsumed = (bool)($connection['consume'] ?? true);
                        if ($shouldBeConsumed) {
                            $queue->resume();
                        }
                    }
                }

                return $jobs;
            }
        );

        $container->bindSingleton(Jobs::class, static function (JobsInterface $jobs): JobsInterface {
            return $jobs;
        });
    }

    private function registerQueue(Container $container): void
    {
        $container->bindSingleton(QueueInterface::class,
            static function (QueueManager $manager): QueueInterface {
                return $manager->getConnection();
            }
        );

        $container->bindSingleton(
            RRQueueInterface::class,
            static function (JobsInterface $jobs, QueueConfig $config): RRQueueInterface {
                return $jobs->connect(
                    $config->getDefault()
                );
            }
        );
    }

    private function initRegistry(ContainerInterface $container, ContainerRegistry $registry, QueueConfig $config)
    {
        $registry = new QueueRegistry($container, $registry);

        foreach ($config->getRegistryHandlers() as $jobType => $handler) {
            $registry->setHandler($jobType, $handler);
        }

        return $registry;
    }

    private function initQueueConfig(EnvironmentInterface $env)
    {
        $this->config->setDefaults(
            QueueConfig::CONFIG,
            [
                'default' => $env->get('QUEUE_CONNECTION', 'sync'),
                'connections' => [
                    'sync' => [
                        'driver' => 'sync',
                    ],
                    'memory' => [
                        'driver' => 'roadrunner',
                        'connector' => new MemoryCreateInfo('default'),
                    ],
                ],
                'registry' => [
                    'handlers' => [
                        MailQueue::JOB_NAME => MailJob::class,
                    ],
                ],
            ]
        );
    }
}
