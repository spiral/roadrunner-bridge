<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\KernelInterface;
use Spiral\Core\ConfigsInterface;
use Spiral\Queue\Bootloader\QueueBootloader as BaseQueueBootloader;
use Spiral\RoadRunner\Jobs\Consumer;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunnerBridge\Config\QueueConfig;
use Spiral\RoadRunnerBridge\Queue\Dispatcher;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\RoadRunnerBridge\Queue\Queue;
use Spiral\RoadRunnerBridge\Queue\RPCPipelineRegistry;
use Spiral\Serializer\Bootloader\SerializerBootloader;

final class QueueBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        RoadRunnerBootloader::class,
        SerializerBootloader::class,
    ];

    protected const SINGLETONS = [
        ConsumerInterface::class => Consumer::class,
        JobsInterface::class => Jobs::class,
        QueueConfig::class => [self::class, 'initConfig'],
        PipelineRegistryInterface::class => RPCPipelineRegistry::class,
    ];

    public function init(
        BaseQueueBootloader $bootloader,
        KernelInterface $kernel,
        Dispatcher $jobs,
    ): void {
        $bootloader->registerDriverAlias(Queue::class, 'roadrunner');
        $kernel->addDispatcher($jobs);
    }

    public function boot(PipelineRegistryInterface $registry): void
    {
        $registry->declareConsumerPipelines();
    }

    private function initConfig(ConfigsInterface $configs): QueueConfig
    {
        $config = $configs->getConfig('queue');

        return new QueueConfig(
            $config['pipelines'] ?? []
        );
    }
}
