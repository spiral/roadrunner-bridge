<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\KernelInterface;
use Spiral\Core\Container;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\Queue\Bootloader\QueueBootloader as BaseQueueBootloader;
use Spiral\Queue\Config\QueueConfig;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\RoadRunnerBridge\Queue\Consumer;
use Spiral\Serializer\Bootloader\SerializerBootloader;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Serializer\SerializerInterface as RRSerializerInterface;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\Queue\Dispatcher;
use Spiral\RoadRunnerBridge\Queue\JobsAdapterSerializer;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\RoadRunnerBridge\Queue\Queue;
use Spiral\RoadRunnerBridge\Queue\RPCPipelineRegistry;
use Spiral\Serializer\SerializerManager;

final class QueueBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        RoadRunnerBootloader::class,
        SerializerBootloader::class,
    ];

    public function init(
        Container $container,
        BaseQueueBootloader $bootloader,
        KernelInterface $kernel,
        Dispatcher $jobs
    ): void {
        $bootloader->registerDriverAlias(Queue::class, 'roadrunner');

        $this->registerPipelineRegistry($container);
        $this->registerJobsSerializer($container);
        $this->registerJobs($container);
        $this->registerConsumer($container);
        $kernel->addDispatcher($jobs);
    }

    private function registerJobsSerializer(Container $container): void
    {
        $container->bindSingleton(
            JobsAdapterSerializer::class,
            static fn (SerializerManager $manager, QueueConfig $config, HandlerRegistryInterface $registry) =>
                new JobsAdapterSerializer(
                    $manager,
                    $registry,
                    $config->getConnections('roadrunner')['roadrunner']['serializerFormat'] ?? null
                )
        );

        $container->bindSingleton(RRSerializerInterface::class, JobsAdapterSerializer::class);
    }

    private function registerConsumer(Container $container): void
    {
        $container->bindSingleton(
            ConsumerInterface::class,
            static fn (JobsAdapterSerializer $serializer, WorkerInterface $worker, QueueConfig $config): Consumer =>
                new Consumer(
                    $serializer,
                    $worker,
                    $config->getConnections('roadrunner')['roadrunner']['pipelines'] ?? []
                )
        );
    }

    private function registerJobs(Container $container): void
    {
        $container->bindSingleton(
            JobsInterface::class,
            static fn (RPCInterface $rpc, RRSerializerInterface $serializer): Jobs => new Jobs($rpc, $serializer)
        );
    }

    private function registerPipelineRegistry(Container $container)
    {
        $container->bind(
            PipelineRegistryInterface::class,
            static fn (
                JobsInterface $jobs,
                JobsAdapterSerializer $serializer,
                array $pipelines,
                array $aliases
            ): PipelineRegistryInterface =>
                new RPCPipelineRegistry($jobs, $serializer, $pipelines, $aliases)
        );
    }
}
