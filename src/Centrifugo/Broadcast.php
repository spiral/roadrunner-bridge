<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Centrifugo;

use RoadRunner\Centrifugo\CentrifugoApiInterface;
use Spiral\Broadcasting\Driver\AbstractBroadcast;

final class Broadcast extends AbstractBroadcast
{
    public function __construct(
        private readonly CentrifugoApiInterface $api
    ) {
    }

    public function publish(iterable|\Stringable|string $topics, iterable|string $messages): void
    {
        /** @var non-empty-string[] $topics */
        $topics = $this->formatTopics($this->toArray($topics));

        /** @var string $message */
        foreach ($this->toArray($messages) as $message) {
            $this->api->broadcast($topics, $message);
        }
    }
}
