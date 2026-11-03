<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Spiral\Queue\Options;
use Spiral\RoadRunner\Jobs\KafkaOptions;
use Spiral\RoadRunner\Jobs\Options as JobsOptions;
use Spiral\RoadRunner\Jobs\OptionsInterface as JobsOptionsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfo;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\Queue\Driver;
use Spiral\RoadRunner\Jobs\Queue\Kafka\ConsumePartition;
use Spiral\RoadRunner\Jobs\Queue\Kafka\ConsumerOffset;
use Spiral\RoadRunner\Jobs\Queue\Kafka\ConsumerOptions;
use Spiral\RoadRunner\Jobs\Queue\Kafka\OffsetType;
use Spiral\RoadRunner\Jobs\Queue\KafkaCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;
use Spiral\RoadRunnerBridge\Queue\Internal\OptionsFactory;
use Spiral\RoadRunnerBridge\Queue\Options as BridgeOptions;
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
        yield 'bridge options without priority and auto-ack' => [new JobsOptions(5), BridgeOptions::delayed(5)];
        yield 'bridge options' => [
            (new JobsOptions(5, 4, true))->withHeader('foo', 'bar'),
            BridgeOptions::onQueue('mail')->withDelay(5)->withPriority(4)->withAutoAck(true)->withHeader('foo', 'bar'),
        ];
    }

    public static function fromCreateInfoDataProvider(): \Traversable
    {
        yield 'other driver, no options' => [null, new MemoryCreateInfo('bar'), null];
        yield 'other driver keeps options' => [new JobsOptions(5), new MemoryCreateInfo('bar'), new JobsOptions(5)];
        yield 'kafka without consumer topics' => [new KafkaOptions('default'), new KafkaCreateInfo('foo', 10), null];
        yield 'kafka takes the single consumer topic' => [
            new KafkaOptions('orders'),
            new KafkaCreateInfo('foo', consumerOptions: new ConsumerOptions(['orders'])),
            null,
        ];
        yield 'kafka takes the topic of its partitions' => [
            new KafkaOptions('orders'),
            new KafkaCreateInfo('foo', consumerOptions: new ConsumerOptions(['orders'], consumePartitions: [
                new ConsumePartition('orders', 0, new ConsumerOffset(OffsetType::AtStart, 1)),
                new ConsumePartition('orders', 1, new ConsumerOffset(OffsetType::AtStart, 1)),
            ])),
            null,
        ];
        yield 'kafka with several consumer topics' => [
            new KafkaOptions('default'),
            new KafkaCreateInfo('foo', consumerOptions: new ConsumerOptions(['orders'], consumePartitions: [
                new ConsumePartition('refunds', 0, new ConsumerOffset(OffsetType::AtStart, 1)),
            ])),
            null,
        ];
        yield 'kafka with a consumer topic regexp' => [
            new KafkaOptions('default'),
            new KafkaCreateInfo('foo', consumerOptions: new ConsumerOptions(['orders.*'], consumeRegexp: true)),
            null,
        ];
        yield 'kafka driver without kafka create info' => [
            new KafkaOptions('default'),
            new CreateInfo(Driver::Kafka, 'foo'),
            null,
        ];
        yield 'kafka keeps kafka options' => [
            new KafkaOptions('orders', 3),
            new KafkaCreateInfo('foo'),
            new KafkaOptions('orders', 3),
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
