<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC;

use Google\Protobuf\Internal\Message;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Method;

final class UnaryCall implements UnaryCallInterface
{
    public function __construct(
        private readonly ContextInterface $context,
        private readonly Method $method,
        private readonly Message $message,
    ) {
    }

    public function getContext(): ContextInterface
    {
        return $this->context;
    }

    public function getMethod(): Method
    {
        return $this->method;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }
}
