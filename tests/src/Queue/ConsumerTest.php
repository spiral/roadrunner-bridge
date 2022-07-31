<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Spiral\App\Job\TestJob;
use Spiral\Queue\Job\ObjectJob;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Payload;
use Spiral\Tests\TestCase;

final class ConsumerTest extends TestCase
{
    /** @dataProvider payloadSerializationDataProvider */
    public function testGetPayloadWithDefaultSerializer(Payload $payload, string $pipeline, string $jobType): void
    {
        $consumer = $this->getContainer()->get(ConsumerInterface::class);

        $ref = new \ReflectionMethod($consumer, 'getPayload');

        $result = $ref->invoke($consumer, $payload, $pipeline, $jobType);

        $this->assertSame(['test' => 'test', 'other' => 'data'], $result);
    }

    public function payloadSerializationDataProvider(): \Traversable
    {
        // default json serializer
        yield [new Payload(\json_encode(['test' => 'test', 'other' => 'data'])), 'memory', 'some'];

        // php serialize from the pipeline config
        yield [new Payload(\serialize(['test' => 'test', 'other' => 'data'])), 'withSerializer', 'some'];

        // Serializer in `memory` pipeline is not set (`json` by default). TestJob set serializer to `serializer`
        yield [new Payload(\serialize(['test' => 'test', 'other' => 'data'])), 'memory', TestJob::class];

        // Serializer in `withSerializer` pipeline is `serializer`. ObjectJob override serializer to `json`
        yield [new Payload(\json_encode(['test' => 'test', 'other' => 'data',])), 'withSerializer', ObjectJob::class];
    }
}
