<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Google\Protobuf\Internal\Message;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Method;

/**
 * The currently processing gRPC request with its context.
 */
interface UnaryCallInterface
{
    public function getContext(): ContextInterface;

    public function getMethod(): Method;

    public function getMessage(): Message;
}
