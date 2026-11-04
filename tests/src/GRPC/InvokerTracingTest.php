<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Mockery as m;
use Spiral\App\GRPC\Ping\PingRequest;
use Spiral\App\GRPC\Ping\PingResponse;
use Spiral\App\GRPC\Ping\PingService;
use Spiral\App\GRPC\Ping\PingServiceInterface;
use Spiral\Grpc\Client\Exception\ServiceClientException;
use Spiral\Interceptors\HandlerInterface;
use Spiral\RoadRunner\GRPC\Context;
use Spiral\RoadRunner\GRPC\Exception\GRPCException;
use Spiral\RoadRunner\GRPC\Exception\NotFoundException;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ResponseHeaders;
use Spiral\RoadRunner\GRPC\StatusCode;
use Spiral\RoadRunnerBridge\GRPC\Internal\Invoker;
use Spiral\Telemetry\TraceKind;
use Spiral\Tests\GRPC\Stub\RecordingTracer;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

final class InvokerTracingTest extends TestCase
{
    #[Test]
    public function tracesCallAsServerSpan(): void
    {
        $tracer = new RecordingTracer();

        $this->invoke($tracer, static fn(): PingResponse => new PingResponse());

        Assert::same($tracer->name, 'ping.dto.PingService/Ping');
        Assert::same($tracer->kind, TraceKind::SERVER);
        Assert::same($tracer->span?->getAttributes(), [
            'rpc.system.name' => 'grpc',
            'rpc.method' => 'ping.dto.PingService/Ping',
            'rpc.response.status_code' => 'OK',
        ]);
        Assert::null($tracer->span->getStatus());
    }

    #[Test]
    public function buildsTracerFromIncomingMetadataOnly(): void
    {
        $tracer = new RecordingTracer();

        $this->invoke($tracer, static fn(): PingResponse => new PingResponse(), [
            'traceparent' => ['00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01'],
        ]);

        Assert::same($tracer->incoming, [
            'traceparent' => ['00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01'],
        ]);
    }

    #[Test]
    public function sendsTraceContextInResponseHeaders(): void
    {
        $tracer = new RecordingTracer(['traceparent' => 'trace', 'baggage' => ['a=1', 'b=2']]);

        $headers = $this->invoke($tracer, static fn(): PingResponse => new PingResponse());

        Assert::same($headers->get('traceparent'), 'trace');
        Assert::same($headers->get('baggage'), 'a=1, b=2');
    }

    #[Test]
    public function marksServerErrorStatus(): void
    {
        $tracer = new RecordingTracer(['traceparent' => 'trace']);
        $headers = new ResponseHeaders();

        try {
            $this->invoke($tracer, static fn() => throw GRPCException::create('down', StatusCode::UNAVAILABLE), headers: $headers);
            Assert::fail('The exception must reach the server.');
        } catch (GRPCException) {
        }

        Assert::same($tracer->span?->getAttribute('rpc.response.status_code'), 'UNAVAILABLE');
        Assert::same($tracer->span->getAttribute('error.type'), 'UNAVAILABLE');
        Assert::same($tracer->span->getStatus()?->code, 'ERROR');
        Assert::same($headers->get('traceparent'), 'trace');
    }

    #[Test]
    public function marksClientErrorStatusToo(): void
    {
        $tracer = new RecordingTracer();

        try {
            $this->invoke($tracer, static fn() => throw new NotFoundException('missing'));
            Assert::fail('The exception must reach the server.');
        } catch (NotFoundException) {
        }

        Assert::same($tracer->span?->getAttribute('rpc.response.status_code'), 'NOT_FOUND');
        Assert::same($tracer->span->getAttribute('error.type'), 'NOT_FOUND');
        Assert::same($tracer->span->getStatus()?->code, 'ERROR');
    }

    #[Test]
    public function keepsDownstreamClientStatusOutOfServerSpan(): void
    {
        $tracer = new RecordingTracer();
        $status = (object) ['code' => StatusCode::NOT_FOUND, 'details' => 'missing', 'metadata' => []];

        try {
            $this->invoke($tracer, static fn() => throw new ServiceClientException($status));
            Assert::fail('The exception must reach the server.');
        } catch (ServiceClientException) {
        }

        Assert::false($tracer->span?->hasAttribute('rpc.response.status_code'));
        Assert::same($tracer->span?->getAttribute('error.type'), ServiceClientException::class);
    }

    #[Test]
    public function recordsExceptionTypeWithoutGrpcStatus(): void
    {
        $tracer = new RecordingTracer();

        Expect::exception(\LogicException::class);

        try {
            $this->invoke($tracer, static fn() => throw new \LogicException('broken'));
        } finally {
            Assert::false($tracer->span?->hasAttribute('rpc.response.status_code'));
            Assert::same($tracer->span?->getAttribute('error.type'), \LogicException::class);
            Assert::same($tracer->span?->getStatus()?->code, 'ERROR');
        }
    }

    #[Test]
    public function tracesBrokenInput(): void
    {
        $tracer = new RecordingTracer();
        $invoker = new Invoker(m::mock(HandlerInterface::class), $this->getContainer(), $tracer);

        try {
            $invoker->invoke(
                m::mock(PingServiceInterface::class),
                Method::parse(new \ReflectionMethod(PingService::class, 'Ping')),
                new Context([]),
                'not a protobuf message',
            );
            Assert::fail('The exception must reach the server.');
        } catch (GRPCException) {
        }

        Assert::same($tracer->span?->getAttribute('rpc.response.status_code'), 'INTERNAL');
    }

    /**
     * @param \Closure(): PingResponse $result
     * @param array<string, list<string>> $metadata
     */
    private function invoke(
        RecordingTracer $tracer,
        \Closure $result,
        array $metadata = [],
        ResponseHeaders $headers = new ResponseHeaders(),
    ): ResponseHeaders {
        $handler = m::mock(HandlerInterface::class);
        $handler->shouldReceive('handle')->once()->andReturnUsing($result);

        $invoker = new Invoker($handler, $this->getContainer(), $tracer);
        $invoker->invoke(
            m::mock(PingServiceInterface::class),
            Method::parse(new \ReflectionMethod(PingService::class, 'Ping')),
            new Context($metadata + [ResponseHeaders::class => $headers]),
            (new PingRequest())->serializeToString(),
        );

        return $headers;
    }
}
