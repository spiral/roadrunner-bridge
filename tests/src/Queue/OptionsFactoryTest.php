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
        yield 'other driver, no options' => [null, new MemoryCreateInfo('bar'), null];
        yield 'other driver keeps options' => [new JobsOptions(5), new MemoryCreateInfo('bar'), new JobsOptions(5)];
        yield 'kafka, no options' => [new KafkaOptions('default'), new KafkaCreateInfo('foo', 10), null];
        yield 'kafka keeps kafka options' => [
            new KafkaOptions('orders', 3),
            new KafkaCreateInfo('foo'),
            new KafkaOptions('orders', 3),
        ];
        yield 'kafka wraps plain options' => [
            (new KafkaOptions('default', 3, 2, true))->withHeader('x', 'y'),
            new KafkaCreateInfo('foo'),
            (new JobsOptions(3, 2, true))->withHeader('x', 'y'),
        ];
    }

    #[DataProvider('createDataProvider')]
    public function create(?JobsOptionsInterface $expected, mixed $from): void
    {
        Assert::equals(OptionsFactory::create($from), $expected);
    }

    #[DataProvider('fromCreateInfoDataProvider')]
    public function fromCreateInfo(
        ?JobsOptionsInterface $expected,
        CreateInfoInterface $createInfo,
        ?JobsOptionsInterface $options,
    ): void {
        Assert::equals(OptionsFactory::fromCreateInfo($createInfo, $options), $expected);
    }
}
