<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal;

use Google\Protobuf\Internal\Message;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunnerBridge\GRPC\UnaryCallInterface;

/**
 * @internal
 */
final readonly class UnaryCall implements UnaryCallInterface
{
    public function __construct(
        private ContextInterface $context,
        private Method $method,
        private Message $message,
    ) {}

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
