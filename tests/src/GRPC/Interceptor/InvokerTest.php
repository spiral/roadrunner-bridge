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
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

final class InvokerTest extends TestCase
{
    #[Test]
    public function invoke(): void
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
            ->withArgs(static function (CallContextInterface $context) use ($service, $input) {
                Assert::same($context->getTarget()->getPath()[0], $service::class);
                Assert::same($context->getTarget()->getPath()[1], 'Ping');
                Assert::instanceOf($context->getArguments()[0], ContextInterface::class);
                Assert::instanceOf($context->getArguments()[1], PingRequest::class);

                return true;
            })->andReturn($output);

        Assert::same($invoker->invoke($service, $method, $ctx, $input->serializeToString()), $output->serializeToString());
    }

    #[Test]
    public function invokeWithBrokenText(): void
    {
        Expect::exception(InvokeException::class);

        $invoker = new Invoker(m::mock(HandlerInterface::class), $this->getContainer());

        $service = m::mock(ServiceInterface::class);
        $method = Method::parse(new \ReflectionMethod(PingService::class, 'Ping'));

        $input = 'input';
        $output = 'output';

        $ctx = m::mock(ContextInterface::class);
        Assert::same($invoker->invoke($service, $method, $ctx, $input), $output);
    }
}
