<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Service\Message;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunnerBridge\GRPC\Internal\UnaryCall;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class UnaryCallTest extends TestCase
{
    #[Test]
    public function getContext(): void
    {
        $ctx = \Mockery::mock(ContextInterface::class)->shouldIgnoreMissing();
        $call = new UnaryCall($ctx, Method::parse(new \ReflectionMethod($this, 'methodForTests')), new Message());

        Assert::same($call->getContext(), $ctx);
    }

    #[Test]
    public function getMethod(): void
    {
        $method = Method::parse(new \ReflectionMethod($this, 'methodForTests'));
        $call = new UnaryCall(
            \Mockery::mock(ContextInterface::class)->shouldIgnoreMissing(),
            $method,
            new Message(),
        );

        Assert::same($call->getMethod(), $method);
    }

    #[Test]
    public function getMessage(): void
    {
        $message = new Message();
        $call = new UnaryCall(
            \Mockery::mock(ContextInterface::class)->shouldIgnoreMissing(),
            Method::parse(new \ReflectionMethod($this, 'methodForTests')),
            $message,
        );

        Assert::same($call->getMessage(), $message);
    }

    public function methodForTests(ContextInterface $ctx, Message $in): Message {}
}
