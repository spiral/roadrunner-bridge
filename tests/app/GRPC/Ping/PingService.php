<?php

declare(strict_types=1);

namespace Spiral\App\GRPC\Ping;

use Spiral\RoadRunner\GRPC;

class PingService implements PingServiceInterface
{
    public function Ping(GRPC\ContextInterface $ctx, PingRequest $in): PingResponse
    {
        return new PingResponse();
    }
}
