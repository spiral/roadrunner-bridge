<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

interface PayloadDeserializerInterface
{
    /**
     * Deserializes the payload of the given task.
     */
    public function deserialize(ReceivedTaskInterface $task): mixed;
}
