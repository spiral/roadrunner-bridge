<?php

declare(strict_types=1);

namespace Spiral\Tests\Centrifugo;

use PHPUnit\Framework\Constraint\IsEqual;
use RoadRunner\Centrifugo\CentrifugoApiInterface;
use Spiral\App\Broadcast\StringableTopic;
use Spiral\RoadRunnerBridge\Centrifugo\Broadcast;
use Spiral\Tests\TestCase;

final class BroadcastTest extends TestCase
{
    /**
     * @dataProvider topicsDataProvider
     */
    public function testPublish(iterable|\Stringable|string $topics, array $expectedTopics): void
    {
        $centrifugoApi = $this->createMock(CentrifugoApiInterface::class);
        $centrifugoApi
            ->expects($this->once())
            ->method('broadcast')
            ->with(new IsEqual($expectedTopics), new IsEqual('bar'));

        $broadcast = new Broadcast($centrifugoApi);

        $broadcast->publish($topics, 'bar');
    }

    /**
     * @dataProvider messagesDataProvider
     */
    public function testPublishWithArrayMessage(iterable $messages): void
    {
        $centrifugoApi = $this->createMock(CentrifugoApiInterface::class);
        $centrifugoApi
            ->expects($this->exactly(2))
            ->method('broadcast')
            ->withConsecutive(
                [new IsEqual(['foo']), new IsEqual('one')],
                [new IsEqual(['foo']), new IsEqual('two')]
            );

        $broadcast = new Broadcast($centrifugoApi);

        $broadcast->publish('foo', $messages);
    }

    public function topicsDataProvider(): \Traversable
    {
        yield ['foo', ['foo']];
        yield [['foo', 'other'], ['foo', 'other']];
        yield [new \ArrayIterator(['foo', 'other']), ['foo', 'other']];
        yield [new StringableTopic('some-topic'), ['some-topic']];
    }

    public function messagesDataProvider(): \Traversable
    {
        yield [['one', 'two']];
        yield [new \ArrayIterator(['one', 'two'])];
    }
}
