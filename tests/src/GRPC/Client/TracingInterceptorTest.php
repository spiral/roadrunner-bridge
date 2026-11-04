<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\Client;

use Mockery as m;
use Spiral\App\GRPC\Ping\PingRequest;
use Spiral\App\GRPC\Ping\PingResponse;
use Spiral\Core\Container;
use Spiral\Core\ContainerScope;
use Spiral\Grpc\Client\Exception\ServiceClientException;
use Spiral\Grpc\Client\Exception\TimeoutException;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\HandlerInterface;
use Spiral\RoadRunnerBridge\GRPC\Client\TracingInterceptor;
use Spiral\Telemetry\TraceKind;
use Spiral\Telemetry\TracerInterface;
use Spiral\Tests\GRPC\Stub\RecordingTracer;
use Testo\Assert;
use Testo\Test;

final class TracingInterceptorTest
{
    #[Test]
    public function tracesCallAsClientSpan(): void
    {
        $tracer = new RecordingTracer();

        $result = $this->call($tracer, static fn(): PingResponse => new PingResponse());

        Assert::instanceOf($result, PingResponse::class);
        Assert::same($tracer->name, 'ping.dto.PingService/Ping');
        Assert::same($tracer->kind, TraceKind::CLIENT);
        Assert::same($tracer->span?->getAttributes(), [
            'rpc.system.name' => 'grpc',
            'rpc.method' => 'ping.dto.PingService/Ping',
            'rpc.response.status_code' => 'OK',
        ]);
    }

    #[Test]
    public function sendsTraceContextInMetadata(): void
    {
        $tracer = new RecordingTracer(['traceparent' => 'trace', 'baggage' => ['a=1']]);
        $sent = null;

        $this->call(
            $tracer,
            static function (CallContextInterface $context) use (&$sent): PingResponse {
                $sent = $context->getArguments()[4];
                return new PingResponse();
            },
            ['authorization' => ['token']],
        );

        Assert::same($sent, [
            'authorization' => ['token'],
            'traceparent' => ['trace'],
            'baggage' => ['a=1'],
        ]);
    }

    #[Test]
    public function marksServiceErrorStatus(): void
    {
        $tracer = new RecordingTracer();
        $status = (object) ['code' => 5, 'details' => 'missing', 'metadata' => []];

        try {
            $this->call($tracer, static fn() => throw new ServiceClientException($status));
            Assert::fail('The exception must reach the caller.');
        } catch (ServiceClientException) {
        }

        Assert::same($tracer->span?->getAttribute('rpc.response.status_code'), 'NOT_FOUND');
        Assert::same($tracer->span->getAttribute('error.type'), 'NOT_FOUND');
        Assert::same($tracer->span->getStatus()?->code, 'ERROR');
    }

    #[Test]
    public function marksTimeoutAsDeadlineExceeded(): void
    {
        $tracer = new RecordingTracer();

        try {
            $this->call($tracer, static fn() => throw new TimeoutException('late'));
            Assert::fail('The exception must reach the caller.');
        } catch (TimeoutException) {
        }

        Assert::same($tracer->span?->getAttribute('rpc.response.status_code'), 'DEADLINE_EXCEEDED');
    }

    #[Test]
    public function recordsExceptionTypeWithoutGrpcStatus(): void
    {
        $tracer = new RecordingTracer();

        try {
            $this->call($tracer, static fn() => throw new \LogicException('broken'));
            Assert::fail('The exception must reach the caller.');
        } catch (\LogicException) {
        }

        Assert::false($tracer->span?->hasAttribute('rpc.response.status_code'));
        Assert::same($tracer->span?->getAttribute('error.type'), \LogicException::class);
    }

    #[Test]
    public function passesCallThroughWithoutTracer(): void
    {
        $handler = m::mock(HandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andReturn($response = new PingResponse());

        Assert::same((new TracingInterceptor())->intercept(self::context(), $handler), $response);
    }

    #[Test]
    public function leavesMetadataAloneWhenNoTracerIsBound(): void
    {
        $handler = m::mock(HandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andReturnUsing(
            static fn(CallContextInterface $context): array => $context->getArguments()[4],
        );

        $sent = ContainerScope::runScope(
            new Container(),
            static fn(): mixed => (new TracingInterceptor())->intercept(self::context(['a' => ['1']]), $handler),
        );

        Assert::same($sent, ['a' => ['1']]);
    }

    /**
     * Arguments in the order spiral/grpc-client builds them.
     *
     * @param array<string, list<string>> $metadata
     */
    private static function context(array $metadata = []): CallContextInterface
    {
        return new CallContext(Target::fromPathString('call'), [
            null,
            '/ping.dto.PingService/Ping',
            new PingRequest(),
            [PingResponse::class, 'decode'],
            $metadata,
            [],
        ]);
    }

    /**
     * @param \Closure(CallContextInterface): mixed $result
     * @param array<string, list<string>> $metadata
     */
    private function call(RecordingTracer $tracer, \Closure $result, array $metadata = []): mixed
    {
        $handler = m::mock(HandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andReturnUsing($result);

        $container = new Container();
        $container->bindSingleton(TracerInterface::class, $tracer);

        return ContainerScope::runScope(
            $container,
            static fn(): mixed => (new TracingInterceptor())->intercept(self::context($metadata), $handler),
        );
    }
}
