<?php

declare(strict_types=1);

namespace Spiral\App\GRPC;

use Spiral\App\GRPC\EchoService\EchoInterface;
use Spiral\App\GRPC\EchoService\Message;
use Spiral\RoadRunner\GRPC\ContextInterface;

class EchoService implements EchoInterface
{
    public function Ping(ContextInterface $ctx, Message $in): Message
    {
        $out = new Message();

        return $out->setMsg('PONG');
    }
}
