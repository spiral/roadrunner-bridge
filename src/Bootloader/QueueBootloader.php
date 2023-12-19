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
use Spiral\RoadRunnerBridge\Queue\PayloadDeserializer;
use Spiral\RoadRunnerBridge\Queue\PayloadDeserializerInterface;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\RoadRunnerBridge\Queue\Queue;
use Spiral\RoadRunnerBridge\Queue\RPCPipelineRegistry;
use Spiral\Serializer\Bootloader\SerializerBootloader;

final class QueueBootloader extends Bootloader
{
    public function defineDependencies(): array
    {
        return [
            RoadRunnerBootloader::class,
            SerializerBootloader::class,
        ];
    }

    public function defineSingletons(): array
    {
        return [
            ConsumerInterface::class => Consumer::class,
            JobsInterface::class => Jobs::class,
            PipelineRegistryInterface::class => RPCPipelineRegistry::class,
            PayloadDeserializerInterface::class => PayloadDeserializer::class,

            QueueConfig::class => static function (ConfigsInterface $configs): QueueConfig {
                $config = $configs->getConfig('queue');

                return new QueueConfig(
                    $config['pipelines'] ?? [],
                );
            },
        ];
    }

    public function init(BaseQueueBootloader $bootloader, KernelInterface $kernel, Dispatcher $jobs): void
    {
        $bootloader->registerDriverAlias(Queue::class, 'roadrunner');
        $kernel->addDispatcher($jobs);
    }

    public function boot(PipelineRegistryInterface $registry): void
    {
        $registry->declareConsumerPipelines();
    }
}
