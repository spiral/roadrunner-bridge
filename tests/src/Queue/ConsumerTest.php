<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Payload;
use Spiral\Tests\TestCase;

final class ConsumerTest extends TestCase
{
    public function testGetPayloadWithDefaultSerializer(): void
    {
        $consumer = $this->getContainer()->get(ConsumerInterface::class);

        $ref = new \ReflectionMethod($consumer, 'getPayload');

        // default json serializer
        $payload = new Payload(\json_encode([
            'test' => 'test',
            'other' => 'data'
        ]));

        $result = $ref->invoke($consumer, $payload, 'memory');

        $this->assertSame(['test' => 'test', 'other' => 'data'], $result);
    }

    public function testGetPayloadWithConfiguredSerializer(): void
    {
        $consumer = $this->getContainer()->get(ConsumerInterface::class);

        $ref = new \ReflectionMethod($consumer, 'getPayload');

        // php serialize from config
        $payload = new Payload(\serialize([
            'test' => 'test',
            'other' => 'data'
        ]));

        $result = $ref->invoke($consumer, $payload, 'withSerializer');

        $this->assertSame(['test' => 'test', 'other' => 'data'], $result);
    }
}
