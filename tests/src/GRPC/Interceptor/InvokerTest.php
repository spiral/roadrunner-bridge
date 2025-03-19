<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\Interceptor;

use Mockery as m;
use Service\PingService;
use Spiral\App\GRPC\Ping\PingRequest;
use Spiral\App\GRPC\Ping\PingResponse;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Exception\InvokeException;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunnerBridge\GRPC\Internal\Invoker;
use Spiral\Tests\TestCase;

final class InvokerTest extends TestCase
{
    public function testInvoke(): void
    {
        $invoker = new Invoker($core = m::mock(HandlerInterface::class), $this->getContainer());

        $service = m::mock(\Spiral\App\GRPC\Ping\PingServiceInterface::class);
        $method = Method::parse(new \ReflectionMethod(\Spiral\App\GRPC\Ping\PingService::class, 'Ping'));

        $input = new PingRequest();
        $output = new PingResponse();

        $ctx = m::mock(ContextInterface::class);
        $core
            ->shouldReceive('handle')
            ->once()
            ->withArgs(function (CallContextInterface $context) use ($service, $input) {
                $this->assertSame($context->getTarget()->getPath()[0], $service::class);
                $this->assertSame('Ping', $context->getTarget()->getPath()[1]);
                $this->assertInstanceOf(ContextInterface::class, $context->getArguments()[0]);
                $this->assertInstanceOf(PingRequest::class, $context->getArguments()[1]);

                return true;
            })->andReturn($output);

        $this->assertSame(
            $output->serializeToString(),
            $invoker->invoke($service, $method, $ctx, $input->serializeToString()),
        );
    }

    public function testInvokeWithBrokenText(): void
    {
        $this->expectException(InvokeException::class);

        $invoker = new Invoker(m::mock(\Spiral\Interceptors\HandlerInterface::class), $this->getContainer());

        $service = m::mock(ServiceInterface::class);
        $method = Method::parse(new \ReflectionMethod(PingService::class, 'Ping'));

        $input = 'input';
        $output = 'output';

        $ctx = m::mock(ContextInterface::class);
        $this->assertSame($output, $invoker->invoke($service, $method, $ctx, $input));
    }
}
