<?php

declare(strict_types=1);

namespace Spiral\App\GRPC;

use Spiral\RoadRunner\GRPC\ContextInterface;

class PingService
{
    public function Ping(ContextInterface $ctx, Message $input): Message
    {
        return $input;
    }
}
