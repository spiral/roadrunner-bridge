<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\Interceptor;

use Spiral\App\GRPC\PingService;
use Spiral\Core\CoreInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunnerBridge\GRPC\Interceptor\Invoker;
use Spiral\Tests\TestCase;
use Mockery as m;

final class InvokerTest extends TestCase
{
    public function testInvoke(): void
    {
        $invoker = new Invoker($core = m::mock(CoreInterface::class));

        $service = m::mock(ServiceInterface::class);
        $method = Method::parse(new \ReflectionMethod(PingService::class, 'Ping'));

        $core
            ->shouldReceive('callAction')
            ->once()
            ->with($service::class, 'Ping', [
                'service' => $service,
                'method' => $method,
                'ctx' => $ctx = m::mock(ContextInterface::class),
                'input' => $input = 'test',
            ])->andReturn('hello');

        $this->assertSame('hello', $invoker->invoke($service, $method, $ctx, $input));
    }
}