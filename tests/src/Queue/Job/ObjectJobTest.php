<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue\Job;

use Psr\Container\ContainerInterface;
use Spiral\Core\Container;
use Spiral\RoadRunnerBridge\Queue\Exception\InvalidArgumentException;
use Spiral\RoadRunnerBridge\Queue\Job\ObjectJob;
use Spiral\Tests\TestCase;

final class ObjectJobTest extends TestCase
{
    public function testPayloadObjectKeyIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Payload `object` key is required.');

        $job = new ObjectJob($this->container);
        $job->handle('foo', 'foo-id', []);
    }

    public function testPayloadObjectValueShouldBeObject()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Payload `object` key value type should be an object.');

        $job = new ObjectJob($this->container);
        $job->handle('foo', 'foo-id', ['object' => 'test']);
    }

    public function testHandleWithHandleMethod()
    {
        $object = new class($this) {
            private $testCase;

            public function __construct($testCase)
            {
                $this->testCase = $testCase;
            }

            public function handle(string $name, string $id, ContainerInterface $container)
            {
                $this->testCase->assertSame('foo', $name);
                $this->testCase->assertSame('foo-id', $id);
                $this->testCase->assertInstanceOf(Container::class, $container);
            }
        };

        $job = new ObjectJob($this->container);

        $job->handle('foo', 'foo-id', ['object' => $object]);
    }

    public function testHandleWithInvokeMethod()
    {
        $object = new class($this) {
            private $testCase;

            public function __construct($testCase)
            {
                $this->testCase = $testCase;
            }

            public function __invoke(string $name, string $id, ContainerInterface $container)
            {
                $this->testCase->assertSame('foo', $name);
                $this->testCase->assertSame('foo-id', $id);
                $this->testCase->assertInstanceOf(Container::class, $container);
            }
        };

        $job = new ObjectJob($this->container);

        $job->handle('foo', 'foo-id', ['object' => $object]);
    }
}
