<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use PHPUnit\Framework\Attributes\DataProvider;
use Spiral\Queue\Options;
use Spiral\RoadRunner\Jobs\KafkaOptions;
use Spiral\RoadRunner\Jobs\Options as JobsOptions;
use Spiral\RoadRunner\Jobs\OptionsInterface as JobsOptionsInterface;
use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\Queue\KafkaCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\RoadRunnerBridge\Queue\OptionsFactory;

final class OptionsFactoryTest extends TestCase
{
    #[DataProvider('createDataProvider')]
    public function testCreate(?JobsOptionsInterface $expected, mixed $from): void
    {
        $this->assertEquals($expected, OptionsFactory::create($from));
    }

    #[DataProvider('fromCreateInfoDataProvider')]
    public function testFromCreateInfo(mixed $expected, CreateInfoInterface $createInfo): void
    {
        $this->assertEquals($expected, OptionsFactory::fromCreateInfo($createInfo));
    }

    public static function createDataProvider(): \Traversable
    {
        yield [null, null];
        yield [new JobsOptions(), new Options()];
        yield [new JobsOptions(5), (new Options())->withDelay(5)];
        yield [
            (new JobsOptions())->withHeader('foo', 'bar'),
            (new Options())->withHeader('foo', 'bar'),
        ];
        yield [
            (new JobsOptions())->withPriority(4)->withDelay(6)->withAutoAck(true),
            (new JobsOptions())->withPriority(4)->withDelay(6)->withAutoAck(true),
        ];
    }

    public static function fromCreateInfoDataProvider(): \Traversable
    {
        yield [null, new MemoryCreateInfo('bar')];
        yield [new KafkaOptions('default'), new KafkaCreateInfo('foo', 10)];
    }
}
