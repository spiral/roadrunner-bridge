<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\App\GRPC\Ping\PingService;
use Spiral\App\GRPC\Ping\PingServiceInterface;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class ServiceLocatorTest extends TestCase
{
    #[Test]
    public function getsServices(): void
    {
        $locator = $this->getContainer()->get(LocatorInterface::class);

        $result = $locator->getServices()[PingServiceInterface::class] ?? null;
        Assert::instanceOf($result, \ReflectionClass::class);
        Assert::notNull($result);
        Assert::same($result->getName(), PingService::class);
    }
}
