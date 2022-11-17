<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo;

use PHPUnit\Framework\Constraint\IsEqual;
use RoadRunner\Centrifugo\Request\RequestInterface;
use RoadRunner\Centrifugo\Request\RequestType;
use Spiral\RoadRunnerBridge\Centrifugo\RegistryInterface;
use Spiral\RoadRunnerBridge\Centrifugo\RequestHandler;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;
use Spiral\Tests\TestCase;

final class RequestHandlerTest extends TestCase
{
    public function testCallAction(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $service = $this->createMock(ServiceInterface::class);
        $service
            ->expects($this->once())
            ->method('handle')
            ->with(new IsEqual($request));

        $registry = $this->createMock(RegistryInterface::class);
        $registry
            ->expects($this->once())
            ->method('getService')
            ->with(new IsEqual(RequestType::Publish))
            ->willReturn($service);

        (new RequestHandler($registry))->callAction(
            '',
            '',
            ['request' => $request, 'type' => RequestType::Publish]
        );
    }
}
