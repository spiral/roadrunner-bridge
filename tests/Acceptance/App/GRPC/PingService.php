<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\App\GRPC;

use Spiral\App\GRPC\Ping\PingRequest;
use Spiral\App\GRPC\Ping\PingResponse;
use Spiral\App\GRPC\Ping\PingServiceInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\ResponseHeaders;

/**
 * `PingResponse` has no fields, so the reply carries the request back in a response header.
 */
final class PingService implements PingServiceInterface
{
    public function Ping(ContextInterface $ctx, PingRequest $in): PingResponse
    {
        $ctx->getValue(ResponseHeaders::class)?->set('x-pinged-by', $in->getFrom());

        return new PingResponse();
    }
}
