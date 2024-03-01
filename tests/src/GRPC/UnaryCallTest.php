<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Service\Message;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunnerBridge\GRPC\UnaryCall;
use Spiral\Tests\TestCase;

final class UnaryCallTest extends TestCase
{
    public function testGetContext(): void
    {
        $ctx = $this->createMock(ContextInterface::class);
        $call = new UnaryCall($ctx, Method::parse(new \ReflectionMethod($this, 'methodForTests')), new Message());

        $this->assertSame($ctx, $call->getContext());
    }

    public function testGetMethod(): void
    {
        $method = Method::parse(new \ReflectionMethod($this, 'methodForTests'));
        $call = new UnaryCall(
            $this->createMock(ContextInterface::class),
            $method,
            new Message()
        );

        $this->assertSame($method, $call->getMethod());
    }

    public function testGetMessage(): void
    {
        $message = new Message();
        $call = new UnaryCall(
            $this->createMock(ContextInterface::class),
            Method::parse(new \ReflectionMethod($this, 'methodForTests')),
            $message
        );

        $this->assertSame($message, $call->getMessage());
    }

    public function methodForTests(ContextInterface $ctx, Message $in): Message
    {
    }
}
