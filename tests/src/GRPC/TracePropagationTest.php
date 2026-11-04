<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Mockery as m;
use Spiral\App\GRPC\Ping\PingRequest;
use Spiral\App\GRPC\Ping\PingResponse;
use Spiral\App\GRPC\Ping\PingService;
use Spiral\App\GRPC\Ping\PingServiceInterface;
use Spiral\Core\ContainerScope;
use Spiral\Core\FactoryInterface;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\HandlerInterface;
use Spiral\RoadRunner\GRPC\Context;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ResponseHeaders;
use Spiral\RoadRunnerBridge\GRPC\Client\TracingInterceptor;
use Spiral\RoadRunnerBridge\GRPC\Internal\Invoker;
use Spiral\RoadRunnerBridge\GRPC\UnaryCallInterface;
use Spiral\Tests\GRPC\Stub\ScopedTracer;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

/**
 * A client call made while a gRPC call is served joins the server trace through the container scopes.
 */
final class TracePropagationTest extends TestCase
{
    #[Test]
    public function clientCallInsideServerCallJoinsItsTrace(): void
    {
        $tracer = new ScopedTracer();
        $sent = null;
        $unaryCall = null;

        $client = m::mock(HandlerInterface::class);
        $client->shouldReceive('handle')->once()->andReturnUsing(
            static function (CallContextInterface $context) use (&$sent): PingResponse {
                $sent = $context->getArguments()[4];
                return new PingResponse();
            },
        );

        $server = m::mock(HandlerInterface::class);
        $server->shouldReceive('handle')->once()->andReturnUsing(
            static function () use ($client, &$unaryCall): PingResponse {
                $unaryCall = ContainerScope::getContainer()?->get(UnaryCallInterface::class);

                return (new TracingInterceptor())->intercept(
                    new CallContext(Target::fromPathString('call'), [
                        null,
                        '/mail.MailService/Send',
                        new PingRequest(),
                        [PingResponse::class, 'decode'],
                        [],
                        [],
                    ]),
                    $client,
                );
            },
        );

        # Made by the factory, as the bootloader does, so the scope it opens follows the current one.
        $invoker = $this->getContainer()->get(FactoryInterface::class)->make(Invoker::class, [
            'handler' => $server,
            'tracerFactory' => $tracer,
        ]);

        # The kernel serves a dispatcher inside a container scope.
        ContainerScope::runScope($this->getContainer(), static fn(): string => $invoker->invoke(
            m::mock(PingServiceInterface::class),
            Method::parse(new \ReflectionMethod(PingService::class, 'Ping')),
            new Context([ResponseHeaders::class => new ResponseHeaders()]),
            (new PingRequest())->serializeToString(),
        ));

        Assert::instanceOf($unaryCall, UnaryCallInterface::class);
        Assert::same($tracer->spans, ['ping.dto.PingService/Ping', 'mail.MailService/Send']);
        Assert::same($sent, ['traceparent' => ['mail.MailService/Send']]);
    }
}
