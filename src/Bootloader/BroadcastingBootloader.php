<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Broadcasting\Bootloader\BroadcastingBootloader as BaseBroadcastingBootloader;
use Spiral\Broadcasting\Bootloader\WebsocketsBootloader;
use Spiral\Broadcasting\Config\BroadcastConfig;
use Spiral\Broadcasting\TopicRegistryInterface;
use Spiral\Core\InvokerInterface;
use Spiral\Core\ScopeInterface;
use Spiral\RoadRunner\Broadcast\Broadcast;
use Spiral\RoadRunner\Broadcast\BroadcastInterface;
use Spiral\RoadRunnerBridge\Broadcasting\RoadRunnerBroadcast;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunnerBridge\Broadcasting\RoadRunnerGuard;

final class BroadcastingBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        RoadRunnerBootloader::class,
        WebsocketsBootloader::class,
    ];

    protected const SINGLETONS = [
        BroadcastInterface::class => [self::class, 'initBroadcast'],
        RoadRunnerGuard::class => [self::class, 'initRoadRunnerGuard'],
    ];

    public function init(BaseBroadcastingBootloader $broadcastingBootloader): void
    {
        $broadcastingBootloader->registerDriverAlias('roadrunner', RoadRunnerBroadcast::class);
    }

    private function initBroadcast(RPCInterface $rpc): BroadcastInterface
    {
        $broadcast = new Broadcast($rpc);

        if (!$broadcast->isAvailable()) {
            throw new \LogicException('The [broadcast] plugin not available');
        }

        return $broadcast;
    }

    private function initRoadRunnerGuard(
        InvokerInterface $invoker,
        ScopeInterface $scope,
        TopicRegistryInterface $registry,
        BroadcastConfig $config
    ): RoadRunnerGuard {
        return new RoadRunnerGuard(
            $invoker,
            $scope,
            $registry,
            $config['authorize']['serverCallback'] ?? null
        );
    }
}
