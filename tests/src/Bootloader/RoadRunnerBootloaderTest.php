<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\Boot\KernelInterface;
use Spiral\Console\ConsoleDispatcher;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\FallbackDispatcher;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class RoadRunnerBootloaderTest extends TestCase
{
    #[Test]
    public function getsEnvironmentInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            EnvironmentInterface::class,
            Environment::class,
        );

        $this->assertContainerBoundAsSingleton(
            Environment::class,
            Environment::class,
        );
    }

    #[Test]
    public function getsRPCInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            RPCInterface::class,
            RPC::class,
        );

        $this->assertContainerBoundAsSingleton(
            RPC::class,
            RPC::class,
        );
    }

    #[Test]
    public function getsWorkerInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            WorkerInterface::class,
            Worker::class,
        );

        $this->assertContainerBoundAsSingleton(
            Worker::class,
            Worker::class,
        );

        // TODO fix problem with rr worker
        \ob_end_flush();
    }

    #[Test]
    public function getsPSR7WorkerInterface(): void
    {
        $this->assertContainerBoundAsSingleton(
            PSR7WorkerInterface::class,
            PSR7Worker::class,
        );

        $this->assertContainerBoundAsSingleton(
            PSR7Worker::class,
            PSR7Worker::class,
        );

        // TODO fix problem with rr worker
        \ob_end_flush();
    }

    #[Test]
    public function failbackDispatcherShouldBeLast(): void
    {
        $kernel = $this->getContainer()->get(KernelInterface::class);
        $dispatchers = (new \ReflectionProperty($kernel, 'dispatchers'))->getValue($kernel);
        $dispatchers = \array_filter($dispatchers, static fn(string $disp): bool => $disp !== ConsoleDispatcher::class);

        Assert::same($dispatchers[\array_key_last($dispatchers)], FallbackDispatcher::class);
    }
}
