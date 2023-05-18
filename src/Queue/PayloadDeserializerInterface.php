<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

interface PayloadDeserializerInterface
{
    public function deserialize(ReceivedTaskInterface $task): mixed;
}
