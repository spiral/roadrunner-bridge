<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use Spiral\Queue\Exception\JobException;
use Spiral\Queue\HandlerInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\SerializerRegistryInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Spiral\RoadRunnerBridge\Queue\Internal\PayloadDeserializer;
use Spiral\RoadRunnerBridge\Queue\Internal\Queue;
use Spiral\Serializer\SerializerInterface;
use Spiral\Tests\Queue\Fixtures\JobHandlerUnionTypeWithString;
use Spiral\Tests\Queue\Fixtures\JobHandlerWithoutClass;
use Spiral\Tests\Queue\Fixtures\JobHandlerWithoutMethod;
use Spiral\Tests\Queue\Fixtures\JobHandlerWithoutPayload;
use Spiral\Tests\Queue\Fixtures\JobHandlerWithoutType;
use Spiral\Tests\Queue\Fixtures\PayloadClass;
use Spiral\Tests\Queue\Fixtures\PayloadClassJobHandler;
use Spiral\Tests\Queue\Fixtures\UnionTypeJobHandler;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

final class PayloadDeserializerTest extends TestCase
{
    private PayloadDeserializer $deserializer;
    private HandlerRegistryInterface|m\MockInterface $registry;
    private SerializerRegistryInterface|m\MockInterface $serializer;

    public static function getHandlersDataProvider(): \Traversable
    {
        yield 'PayloadClassJobHandler' => [new PayloadClassJobHandler()];
        yield 'UnionTypeJobHandlers' => [new UnionTypeJobHandler()];
    }

    public static function getInvalidHandlersDataProvider(): \Traversable
    {
        yield 'JobHandlerWithoutMethod' => [new JobHandlerWithoutMethod()];
        yield 'JobHandlerWithoutClass' => [new JobHandlerWithoutClass()];
        yield 'JobHandlerWithoutPayload' => [new JobHandlerWithoutPayload()];
        yield 'JobHandlerWithoutType' => [new JobHandlerWithoutType()];
    }

    public static function getWrongDataFromHeadersDataProvider(): \Traversable
    {
        yield 'string' => ['string'];
        yield 'empty-string' => [''];
        yield 'non-existing-class' => ['Foo'];
        yield 'int' => [1];
    }

    #[Test]
    public function getClassFromHeaders(): void
    {
        $task = m::mock(ReceivedTaskInterface::class);

        $task->shouldReceive('hasHeader')
            ->once()
            ->with(Queue::SERIALIZED_CLASS_HEADER_KEY)
            ->andReturnTrue();

        $task->shouldReceive('getHeaderLine')
            ->once()
            ->with(Queue::SERIALIZED_CLASS_HEADER_KEY)
            ->andReturn(PayloadClass::class);

        $task->shouldReceive('getName')->once()->andReturn($name = 'foo-task');
        $task->shouldReceive('getPayload')->once()->andReturn($payload = '{"foo":"bar"}');

        $this->serializer->shouldReceive('getSerializer')->once()->with($name)
            ->andReturn($serializer = m::mock(SerializerInterface::class));

        $serializer->shouldReceive('unserialize')->once()->with($payload, PayloadClass::class)
            ->andReturn($unserialized = 'unserialized-payload');

        Assert::same($this->deserializer->deserialize($task), $unserialized);
    }

    #[DataProvider('getWrongDataFromHeadersDataProvider')]
    #[Test]
    public function getWrongDataFromHeaders(mixed $header): void
    {
        $task = m::mock(ReceivedTaskInterface::class);

        $task->shouldReceive('hasHeader')
            ->once()
            ->with(Queue::SERIALIZED_CLASS_HEADER_KEY)
            ->andReturnTrue();

        $task->shouldReceive('getHeaderLine')
            ->once()
            ->with(Queue::SERIALIZED_CLASS_HEADER_KEY)
            ->andReturn($header);

        $task->shouldReceive('getName')->once()->andReturn($name = 'foo-task');
        $task->shouldReceive('getPayload')->once()->andReturn($payload = '{"foo":"bar"}');

        $this->serializer->shouldReceive('getSerializer')->once()->with($name)
            ->andReturn($serializer = m::mock(SerializerInterface::class));

        $this->registry->shouldReceive('getHandler')
            ->once()
            ->with($name)
            ->andReturn(new JobHandlerWithoutMethod());

        $serializer->shouldReceive('unserialize')->once()->with($payload)
            ->andReturn($unserialized = 'unserialized-payload');

        Assert::same($this->deserializer->deserialize($task), $unserialized);
    }

    #[DataProvider('getHandlersDataProvider')]
    #[Test]
    public function getClassFromHandler(HandlerInterface $handler): void
    {
        $task = m::mock(ReceivedTaskInterface::class);

        $task->shouldReceive('hasHeader')
            ->once()
            ->with(Queue::SERIALIZED_CLASS_HEADER_KEY)
            ->andReturnFalse();

        $task->shouldReceive('getName')->once()->andReturn($name = 'foo-task');
        $task->shouldReceive('getPayload')->once()->andReturn($payload = '{"foo":"bar"}');

        $this->serializer->shouldReceive('getSerializer')->once()->with($name)
            ->andReturn($serializer = m::mock(SerializerInterface::class));

        $this->registry->shouldReceive('getHandler')
            ->once()
            ->with($name)
            ->andReturn($handler);

        $serializer->shouldReceive('unserialize')->once()->with($payload, PayloadClass::class)
            ->andReturn($unserialized = 'unserialized-payload');

        Assert::same($this->deserializer->deserialize($task), $unserialized);
    }

    #[Test]
    public function getClassFromHandlerShouldIgnoreMissedHandler(): void
    {
        $task = m::mock(ReceivedTaskInterface::class);

        $task->shouldReceive('hasHeader')
            ->once()
            ->with(Queue::SERIALIZED_CLASS_HEADER_KEY)
            ->andReturnFalse();

        $task->shouldReceive('getName')->once()->andReturn($name = 'foo-task');
        $task->shouldReceive('getPayload')->once()->andReturn($payload = '{"foo":"bar"}');

        $this->serializer->shouldReceive('getSerializer')->once()->with($name)
            ->andReturn($serializer = m::mock(SerializerInterface::class));

        $this->registry->shouldReceive('getHandler')
            ->once()
            ->with($name)
            ->andThrow(JobException::class);

        $serializer->shouldReceive('unserialize')->once()->with($payload)
            ->andReturn($unserialized = 'unserialized-payload');

        Assert::same($this->deserializer->deserialize($task), $unserialized);
    }

    #[DataProvider('getInvalidHandlersDataProvider')]
    #[Test]
    public function getClassFromInvalidHandler(HandlerInterface $handler): void
    {
        $task = m::mock(ReceivedTaskInterface::class);

        $task->shouldReceive('hasHeader')
            ->once()
            ->with(Queue::SERIALIZED_CLASS_HEADER_KEY)
            ->andReturnFalse();

        $task->shouldReceive('getName')->once()->andReturn($name = 'foo-task');
        $task->shouldReceive('getPayload')->once()->andReturn($payload = '{"foo":"bar"}');

        $this->serializer->shouldReceive('getSerializer')->once()->with($name)
            ->andReturn($serializer = m::mock(SerializerInterface::class));

        $this->registry->shouldReceive('getHandler')
            ->once()
            ->with($name)
            ->andReturn($handler);

        $serializer->shouldReceive('unserialize')->once()->with($payload)
            ->andReturn($unserialized = 'unserialized-payload');

        Assert::same($this->deserializer->deserialize($task), $unserialized);
    }

    #[Test]
    public function usePayloadAsStringInCasIfStringExpected(): void
    {
        $task = m::mock(ReceivedTaskInterface::class);

        $task->shouldReceive('hasHeader')
            ->once()
            ->with(Queue::SERIALIZED_CLASS_HEADER_KEY)
            ->andReturnFalse();

        $this->serializer->shouldReceive('getSerializer')->once()->with('foo-task')
            ->andReturn($serializer = m::mock(SerializerInterface::class));

        $task->shouldReceive('getName')->once()->andReturn($name = 'foo-task');
        $task->shouldReceive('getPayload')->once()->andReturn($payload = '{"foo":"bar"}');

        $this->registry->shouldReceive('getHandler')
            ->once()
            ->with($name)
            ->andReturn(new JobHandlerUnionTypeWithString());

        Assert::same($this->deserializer->deserialize($task), $payload);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->deserializer = new PayloadDeserializer(
            $this->registry = m::mock(HandlerRegistryInterface::class),
            $this->serializer = m::mock(SerializerRegistryInterface::class),
        );
    }
}
