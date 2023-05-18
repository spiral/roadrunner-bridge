<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo;

use PHPUnit\Framework\Attributes\DataProvider;
use RoadRunner\Centrifugo\CentrifugoApiInterface;
use Spiral\App\Broadcast\StringableTopic;
use Spiral\RoadRunnerBridge\Centrifugo\Broadcast;
use Spiral\Tests\TestCase;

final class BroadcastTest extends TestCase
{
    #[DataProvider('topicsDataProvider')]
    public function testPublish(iterable|\Stringable|string $topics, array $expectedTopics): void
    {
        $centrifugoApi = \Mockery::mock(CentrifugoApiInterface::class);
        $centrifugoApi->shouldReceive('broadcast')->once()->with($expectedTopics, 'bar');
        $broadcast = new Broadcast($centrifugoApi);

        $broadcast->publish($topics, 'bar');
    }

    #[DataProvider('messagesDataProvider')]
    public function testPublishWithArrayMessage(iterable $messages): void
    {
        $centrifugoApi = \Mockery::mock(CentrifugoApiInterface::class);

        $centrifugoApi->shouldReceive('broadcast')->once()->with(['foo'], 'one');
        $centrifugoApi->shouldReceive('broadcast')->once()->with(['foo'], 'two');

        $broadcast = new Broadcast($centrifugoApi);
        $broadcast->publish('foo', $messages);
    }

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
}
