<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Broadcasting;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Broadcasting\Driver\AbstractBroadcast;
use Spiral\Broadcasting\GuardInterface;
use Spiral\RoadRunner\Broadcast\BroadcastInterface;
use Spiral\RoadRunner\Broadcast\TopicInterface;

final class RoadRunnerBroadcast extends AbstractBroadcast implements GuardInterface
{
    private BroadcastInterface $broadcast;
    private GuardInterface $guard;

    public function __construct(
        BroadcastInterface $broadcast,
        GuardInterface $guard
    ) {
        $this->broadcast = $broadcast;
        $this->guard = $guard;
    }

    /**
     * @inheritDoc
     * @throws \Spiral\RoadRunner\Broadcast\Exception\BroadcastException
     */
    public function publish($topics, $messages): void
    {
        $this->broadcast->publish($topics, $messages);
    }

    public function join($topics): TopicInterface
    {
        return $this->broadcast->join($topics);
    }

    public function authorize(ServerRequestInterface $request): ResponseInterface
    {
        return $this->guard->authorize($request);
    }
}