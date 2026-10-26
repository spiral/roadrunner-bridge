<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo;

use RoadRunner\Centrifugo\CentrifugoApiInterface;
use Spiral\App\Broadcast\StringableTopic;
use Spiral\RoadRunnerBridge\Centrifugo\Internal\Broadcast;
use Spiral\Tests\TestCase;
use Testo\Data\DataProvider;
use Testo\Test;

final class BroadcastTest extends TestCase
{
    public static function topicsDataProvider(): \Traversable
    {
        yield ['foo', ['foo']];
        yield [['foo', 'other'], ['foo', 'other']];
        yield [new \ArrayIterator(['foo', 'other']), ['foo', 'other']];
        yield [new StringableTopic('some-topic'), ['some-topic']];
    }

    public static function messagesDataProvider(): \Traversable
    {
        yield [['one', 'two']];
        yield [new \ArrayIterator(['one', 'two'])];
    }

    #[DataProvider('topicsDataProvider')]
    #[Test]
    public function publish(iterable|\Stringable|string $topics, array $expectedTopics): void
    {
        $centrifugoApi = \Mockery::mock(CentrifugoApiInterface::class);
        $centrifugoApi->shouldReceive('broadcast')->once()->with(\Mockery::isEqual($expectedTopics), \Mockery::isEqual('bar'), \Mockery::andAnyOtherArgs());

        $broadcast = new Broadcast($centrifugoApi);

        $broadcast->publish($topics, 'bar');
    }

    #[DataProvider('messagesDataProvider')]
    #[Test]
    public function publishWithArrayMessage(iterable $messages): void
    {
        $centrifugoApi = \Mockery::mock(CentrifugoApiInterface::class);
        $centrifugoApi->shouldReceive('broadcast')->once()->ordered()->with(['foo'], 'one');
        $centrifugoApi->shouldReceive('broadcast')->once()->ordered()->with(['foo'], 'two');

        $broadcast = new Broadcast($centrifugoApi);

        $broadcast->publish('foo', $messages);
    }
}
