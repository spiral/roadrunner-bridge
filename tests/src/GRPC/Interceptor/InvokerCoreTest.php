<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\Interceptor;

use Service\PingService;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunnerBridge\GRPC\Interceptor\InvokerCore;
use Spiral\Tests\TestCase;
use Mockery as m;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\GRPC\Method;

final class InvokerCoreTest extends TestCase
{
    public function testCallAction(): void
    {
        $core = new InvokerCore($invoker = m::mock(InvokerInterface::class));

        $invoker->shouldReceive('invoke')
            ->once()
            ->with(
                $service = m::mock(ServiceInterface::class),
                $method = Method::parse(new \ReflectionMethod(PingService::class, 'Ping')),
                $ctx = m::mock(ContextInterface::class),
                'some'
            );

        $core->callAction('foo', 'sync', [
            'service' => $service,
            'method' => $method,
            'ctx' => $ctx,
            'input' => 'some',
        ]);
    }
}
