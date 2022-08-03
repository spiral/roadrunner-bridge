<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\RoadRunner\Metrics\Metrics;
use Spiral\RoadRunner\Metrics\MetricsInterface;
use Spiral\Tests\TestCase;

final class MetricsBootloaderTest extends TestCase
{
    public function testMetricsInterfaceBinding(): void
    {
        $this->assertContainerBoundAsSingleton(MetricsInterface::class, Metrics::class);
    }
}
