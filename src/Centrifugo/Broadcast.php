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
        $topics = $this->formatTopics($this->toArray($topics));

        /** @var string $message */
        foreach ($this->toArray($messages) as $message) {
            \assert(\is_string($message), 'Message argument must be a type of string');
            $this->api->broadcast($topics, $message);
        }
    }
}
