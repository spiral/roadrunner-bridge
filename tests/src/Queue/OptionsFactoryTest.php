<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Spiral\Queue\Options;
use Spiral\RoadRunner\Jobs\KafkaOptions;
use Spiral\RoadRunner\Jobs\Options as JobsOptions;
use Spiral\RoadRunner\Jobs\OptionsInterface as JobsOptionsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\Queue\KafkaCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\RoadRunnerBridge\Queue\Internal\OptionsFactory;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Test;

#[Test]
final class OptionsFactoryTest
{
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

    #[DataProvider('createDataProvider')]
    public function create(?JobsOptionsInterface $expected, mixed $from): void
    {
        Assert::equals(OptionsFactory::create($from), $expected);
    }

    #[DataProvider('fromCreateInfoDataProvider')]
    public function fromCreateInfo(mixed $expected, CreateInfoInterface $createInfo): void
    {
        Assert::equals(OptionsFactory::fromCreateInfo($createInfo), $expected);
    }
}
