<?php

declare(strict_types=1);

namespace Service;

use Spiral\RoadRunner\GRPC\ContextInterface;

class PingService implements PingInterface
{
    public function Ping(ContextInterface $ctx, Message $in): Message
    {
        $out = new Message();

        return $out->setMsg('PONG');
    }
}
