<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\Tests\TestCase;
use Spiral\RoadRunner\Services\Manager;

final class ServicesBootloaderTest extends TestCase
{
    public function testHandlerBinding(): void
    {
        $this->assertContainerBoundAsSingleton(Manager::class, Manager::class);
    }
}
