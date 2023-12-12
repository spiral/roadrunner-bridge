<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\Interceptor;

use Mockery as m;
use Service\PingService;
use Spiral\Core\CoreInterface;
use Service\Message;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Exception\InvokeException;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunnerBridge\GRPC\Interceptor\Invoker;
use Spiral\Tests\TestCase;

final class InvokerTest extends TestCase
{
    public function testInvoke(): void
    {
        $invoker = new Invoker($core = m::mock(CoreInterface::class));

        $service = m::mock(ServiceInterface::class);
        $method = Method::parse(new \ReflectionMethod(PingService::class, 'Ping'));

        $input = (new Message(['msg' => 'hello']))->serializeToString();
        $output = (new Message(['msg' => 'world']))->serializeToString();

        $ctx = m::mock(ContextInterface::class);
        $core
            ->shouldReceive('callAction')
            ->once()
            ->withArgs(function (string $class, string $method, array $params) use ($service, $input) {
                $this->assertSame($class, $service::class);
                $this->assertSame('Ping', $method);
                $this->assertInstanceOf(ContextInterface::class, $params['ctx']);
                $this->assertSame($input, $params['input']);
                $this->assertInstanceOf(Message::class, $params['message']);
                $this->assertSame('hello', $params['message']->getMsg());

                return true;
            })->andReturn($output);

        $this->assertSame($output, $invoker->invoke($service, $method, $ctx, $input));
    }

    public function testInvokeWithBrokenText(): void
    {
        $this->expectException(InvokeException::class);

        $invoker = new Invoker(m::mock(CoreInterface::class));

        $service = m::mock(ServiceInterface::class);
        $method = Method::parse(new \ReflectionMethod(PingService::class, 'Ping'));

        $input = 'input';
        $output = 'output';

        $ctx = m::mock(ContextInterface::class);
        $this->assertSame($output, $invoker->invoke($service, $method, $ctx, $input));
    }
}
