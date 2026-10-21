<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\RoadRunner\Metrics\Metrics;
use Spiral\RoadRunner\Metrics\MetricsInterface;
use Spiral\Tests\TestCase;
use Testo\Test;

final class MetricsBootloaderTest extends TestCase
{
    #[Test]
    public function metricsInterfaceBinding(): void
    {
        $this->assertContainerBoundAsSingleton(MetricsInterface::class, Metrics::class);
    }
}
