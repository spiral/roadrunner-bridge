<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Spiral\App\Job\TestJob;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Payload;
use Spiral\Tests\TestCase;

final class ConsumerTest extends TestCase
{
    /** @dataProvider payloadSerializationDataProvider */
    public function testGetPayload(Payload $payload, string $jobType): void
    {
        $consumer = $this->getContainer()->get(ConsumerInterface::class);

        $ref = new \ReflectionMethod($consumer, 'getPayload');

        $result = $ref->invoke($consumer, $payload, $jobType);

        $this->assertSame(['test' => 'test', 'other' => 'data'], $result);
    }

    public function payloadSerializationDataProvider(): \Traversable
    {
        // default json serializer
        yield [new Payload(\json_encode(['test' => 'test', 'other' => 'data'])), 'some'];

        // TestJob set serializer to `serializer`
        yield [new Payload(\serialize(['test' => 'test', 'other' => 'data'])), TestJob::class];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        ob_end_clean();
    }
}
