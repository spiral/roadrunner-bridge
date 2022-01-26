<?php

declare(strict_types=1);

namespace Spiral\Tests\Config;

use Spiral\RoadRunnerBridge\Config\QueueConfig;
use Spiral\RoadRunnerBridge\Queue\Exception\InvalidArgumentException;
use Spiral\Tests\TestCase;

final class QueueConfigTest extends TestCase
{
    public function testGetsAliases(): void
    {
        $config = new QueueConfig([
            'aliases' => ['foo', 'bar'],
        ]);

        $this->assertSame(['foo', 'bar'], $config->getAliases());
    }

    public function testGetNotExistsAliases(): void
    {
        $config = new QueueConfig();

        $this->assertSame([], $config->getAliases());
    }

    public function testGetsDefaultDriver(): void
    {
        $config = new QueueConfig([
            'default' => 'foo',
        ]);
        $this->assertSame('foo', $config->getDefaultDriver());
    }

    public function testGetsEmptyDefaultDriverShouldThrowAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Default queue pipeline is not defined.');

        $config = new QueueConfig();

        $config->getDefaultDriver();
    }

    public function testGetsNonStringDefaultDriverShouldThrowAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Default queue pipeline config value must be a string');

        $config = new QueueConfig(['default' => ['foo']]);

        $config->getDefaultDriver();
    }

    public function testGetsDriverAliases(): void
    {
        $config = new QueueConfig([
            'driverAliases' => ['foo', 'bar'],
        ]);

        $this->assertSame(['foo', 'bar'], $config->getDriverAliases());
    }

    public function testGetNotExistsDriverAliases(): void
    {
        $config = new QueueConfig();

        $this->assertSame([], $config->getDriverAliases());
    }

    public function testGetsPipelinesWithoutDriver(): void
    {
        $config = new QueueConfig([
            'pipelines' => ['foo', 'bar'],
        ]);

        $this->assertSame(['foo', 'bar'], $config->getPipelines());
    }

    public function testGetsNotExistsPipelines(): void
    {
        $config = new QueueConfig();

        $this->assertSame([], $config->getPipelines());
    }

    public function testGetsPipelinesWithSpecificDriverAlias(): void
    {
        $config = new QueueConfig([
            'pipelines' => [
                'foo' => [
                    'driver' => 'baz',
                ],
                'baz' => [
                    'driver' => 'foo',
                ],
                'bar' => [],
            ],
            'driverAliases' => [
                'alias' => 'baz',
            ],
        ]);

        $this->assertSame([
            'foo' => [
                'driver' => 'baz',
            ],
        ], $config->getPipelines('alias'));
    }

    public function testGetsPipelinesWithSpecificDriver(): void
    {
        $config = new QueueConfig([
            'pipelines' => [
                'foo' => [
                    'driver' => 'alias',
                ],
                'baz' => [
                    'driver' => 'baz',
                ],
                'bar' => [],
            ],
            'driverAliases' => [
                'alias' => 'baz',
            ],
        ]);

        $this->assertSame([
            'foo' => [
                'driver' => 'alias',
            ],
            'baz' => [
                'driver' => 'baz',
            ],
        ], $config->getPipelines('baz'));
    }

    public function testGetsPipeline(): void
    {
        $config = new QueueConfig([
            'pipelines' => [
                'foo' => [
                    'driver' => 'alias',
                ],
                'baz' => [
                    'driver' => 'bar',
                ],
                'bar' => [],
            ],
            'driverAliases' => [
                'alias' => 'baz',
            ],
        ]);

        $this->assertSame([
            'driver' => 'baz',
        ], $config->getPipeline('foo'));
    }

    public function testGetsNonExistPipelineShouldThrowAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Queue pipeline with given name `foo` is not defined.');

        $config = new QueueConfig();
        $config->getPipeline('foo');
    }

    public function testGetsPipelineWithoutDriverShouldThrowAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Driver for queue pipeline `foo` is not defined.');

        $config = new QueueConfig([
            'pipelines' => [
                'foo' => [],
            ],
        ]);

        $config->getPipeline('foo');
    }

    public function testGetsPipelineWithWrongDriverValueTypeShouldThrowAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Driver for queue pipeline `foo` value must be a string');

        $config = new QueueConfig([
            'pipelines' => [
                'foo' => [
                    'driver' => []
                ],
            ],
        ]);

        $config->getPipeline('foo');
    }

    public function testGetsPipelineWithWrongDriverAliasValueTypeShouldThrowAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectErrorMessage('Driver alias for queue pipeline `foo` value must be a string');

        $config = new QueueConfig([
            'pipelines' => [
                'foo' => [
                    'driver' => 'bar'
                ],
            ],
            'driverAliases' => [
                'bar' => []
            ]
        ]);

        $config->getPipeline('foo');
    }

    public function testGetsRegistryHandlers(): void
    {
        $config = new QueueConfig([
            'registry' => [
                'handlers' => ['foo', 'bar'],
            ]
        ]);

        $this->assertSame(['foo', 'bar'], $config->getRegistryHandlers());
    }

    public function testGetsNotExistsRegistryHandlers(): void
    {
        $config = new QueueConfig();

        $this->assertSame([], $config->getRegistryHandlers());
    }
}
