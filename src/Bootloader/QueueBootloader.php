<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\KernelInterface;
use Spiral\Core\Container;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\Queue\Bootloader\QueueBootloader as BaseQueueBootloader;
use Spiral\Queue\SerializerInterface;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\Serializer\SerializerInterface as RRSerializerInterface;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\Queue\Consumer;
use Spiral\RoadRunnerBridge\Queue\Dispatcher;
use Spiral\RoadRunnerBridge\Queue\JobsAdapterSerializer;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\RoadRunnerBridge\Queue\Queue;
use Spiral\RoadRunnerBridge\Queue\RPCPipelineRegistry;

final class QueueBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        RoadRunnerBootloader::class,
        BaseQueueBootloader::class,
    ];

    public function boot(
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
        $container->bindSingleton(RRSerializerInterface::class, static function (SerializerInterface $serializer) {
            return new JobsAdapterSerializer($serializer);
        });
    }

    private function registerConsumer(Container $container): void
    {
        $container->bindSingleton(
            ConsumerInterface::class,
            static function (JobsAdapterSerializer $serializer, WorkerInterface $worker): Consumer {
                return new Consumer($serializer, $worker);
            }
        );
    }

    private function registerJobs(Container $container): void
    {
        $container->bindSingleton(
            JobsInterface::class,
            static function (RPCInterface $rpc, RRSerializerInterface $serializer): Jobs {
                return new Jobs($rpc, $serializer);
            }
        );
    }

    private function registerPipelineRegistry(Container $container)
    {
        $container->bind(
            PipelineRegistryInterface::class,
            static function (
                JobsInterface $jobs,
                array $pipelines,
                array $aliases,
                JobsAdapterSerializer $serializer
            ): PipelineRegistryInterface {
                return new RPCPipelineRegistry($jobs, $pipelines, $aliases, 60, $serializer);
            }
        );
    }
}
