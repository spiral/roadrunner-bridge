<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Mockery as m;
use PHPUnit\Framework\Attributes\DataProvider;
use Spiral\Queue\HandlerInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\SerializerRegistryInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Spiral\RoadRunnerBridge\Queue\PayloadDeserializer;
use Spiral\RoadRunnerBridge\Queue\Queue;
use Spiral\Serializer\SerializerInterface;
use Spiral\Tests\Queue\Fixtures\JobHandlerUnionTypeWithoutClass;
use Spiral\Tests\Queue\Fixtures\JobHandlerWithoutClass;
use Spiral\Tests\Queue\Fixtures\JobHandlerWithoutMethod;
use Spiral\Tests\Queue\Fixtures\JobHandlerWithoutPayload;
use Spiral\Tests\Queue\Fixtures\JobHandlerWithoutType;
use Spiral\Tests\Queue\Fixtures\PayloadClass;
use Spiral\Tests\Queue\Fixtures\PayloadClassJobHandler;
use Spiral\Tests\Queue\Fixtures\UnionTypeJobHandler;
use Spiral\Tests\TestCase;

final class PayloadDeserializerTest extends TestCase
{
    private PayloadDeserializer $deserializer;
    private HandlerRegistryInterface|\Mockery\MockInterface $registry;
    private SerializerRegistryInterface|\Mockery\MockInterface $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deserializer = new PayloadDeserializer(
            $this->registry = m::mock(HandlerRegistryInterface::class),
            $this->serializer = m::mock(SerializerRegistryInterface::class),
        );
    }

    public function testGetClassFromHeaders(): void
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

        $this->assertSame($unserialized, $this->deserializer->deserialize($task));
    }

    #[DataProvider('getWrongDataFromHeadersDataProvider')]
    public function testGetWrongDataFromHeaders(mixed $header): void
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

        $this->assertSame($unserialized, $this->deserializer->deserialize($task));
    }

    #[DataProvider('getHandlersDataProvider')]
    public function testGetClassFromHandler(HandlerInterface $handler): void
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

        $this->assertSame($unserialized, $this->deserializer->deserialize($task));
    }

    #[DataProvider('getInvalidHandlersDataProvider')]
    public function testGetClassFromInvalidHandler(HandlerInterface $handler): void
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

        $this->assertSame($unserialized, $this->deserializer->deserialize($task));
    }

    public static function getHandlersDataProvider(): \Traversable
    {
        yield 'PayloadClassJobHandler' => [new PayloadClassJobHandler()];
        yield 'UnionTypeJobHandlers' => [new UnionTypeJobHandler()];
    }

    public static function getInvalidHandlersDataProvider(): \Traversable
    {
        yield 'JobHandlerWithoutMethod' => [new JobHandlerWithoutMethod()];
        yield 'JobHandlerWithoutClass' => [new JobHandlerWithoutClass()];
        yield 'JobHandlerUnionTypeWithoutClass' => [new JobHandlerUnionTypeWithoutClass()];
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
}
