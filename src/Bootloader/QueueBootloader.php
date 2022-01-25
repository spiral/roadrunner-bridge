<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Boot\KernelInterface;
use Spiral\Bootloader\ServerBootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Core\Container;
use Spiral\Core\FactoryInterface;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\QueueInterface;
use Spiral\Queue\SerializerInterface;
use Spiral\RoadRunner\Jobs\Consumer;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunner\Jobs\JobsInterface;
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
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\RoadRunnerBridge\Queue\Queue;
use Spiral\RoadRunnerBridge\Queue\QueueManager;
use Spiral\RoadRunnerBridge\Queue\QueueRegistry;
use Spiral\RoadRunnerBridge\Queue\JobsAdapterSerializer;
use Spiral\RoadRunnerBridge\Queue\RPCPipelineRegistry;
use Spiral\RoadRunnerBridge\Queue\ShortCircuit;
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
        FailedJobHandlerInterface::class => LogFailedJobHandler::class,
        PipelineRegistryInterface::class => RPCPipelineRegistry::class,
        QueueManager::class => [self::class, 'initQueueManager'],
        QueueRegistry::class => [self::class, 'initRegistry'],
    ];

    private ConfiguratorInterface $config;

    public function __construct(ConfiguratorInterface $config)
    {
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

    public function registerDriverAlias(string $driverClass, string $alias): void
    {
        $this->config->modify(
            'queue',
            new Append('driverAliases', $alias, $driverClass)
        );
    }

    private function initQueueManager(FactoryInterface $factory): QueueManager
    {
        $this->registerDriverAlias(ShortCircuit::class, 'sync');
        $this->registerDriverAlias(Queue::class, 'roadrunner');

        return $factory->make(QueueManager::class);
    }

    private function registerJobsSerializer(Container $container): void
    {
        $container->bindSingleton(SerializerInterface::class, static function () {
            return new DefaultSerializer();
        });

        $container->bindSingleton(RRSerializerInterface::class, static function (SerializerInterface $serializer) {
            return new JobsAdapterSerializer($serializer);
        });
    }

    private function registerConsumer(Container $container): void
    {
        $container->bindSingleton(
            ConsumerInterface::class,
            static function (WorkerInterface $worker, RRSerializerInterface $serializer): Consumer {
                return new Consumer($worker, $serializer);
            }
        );
    }

    private function registerJobs(Container $container): void
    {
        $container->bindSingleton(
            JobsInterface::class,
            static function (RPCInterface $rpc, QueueConfig $config, RRSerializerInterface $serializer): Jobs {
                return new Jobs($rpc, $serializer);
            }
        );
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
                    $config->getDefaultDriver()
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
                        'consume' => true,
                    ],
                ],
                'registry' => [
                    'handlers' => [
                        MailQueue::JOB_NAME => MailJob::class,
                    ],
                ],
                'driverAliases' => [],
            ]
        );
    }
}
