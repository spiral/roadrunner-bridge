<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Spiral\App\Job\TestJob;
use Spiral\Queue\Job\ObjectJob;
use Spiral\RoadRunnerBridge\Queue\JobsAdapterSerializer;
use Spiral\Tests\TestCase;

final class JobsAdapterSerializerTest extends TestCase
{
    public function testWithJobType(): void
    {
        $serializer = $this->getContainer()->get(JobsAdapterSerializer::class);

        $ref = new \ReflectionProperty($serializer, 'format');

        $this->assertNull($ref->getValue($serializer));
        $this->assertSame('serializer', $ref->getValue($serializer->withJobType(TestJob::class)));
        $this->assertSame('json', $ref->getValue($serializer->withJobType(ObjectJob::class)));
        $this->assertNull($ref->getValue($serializer->withJobType('some')));
    }

    public function testWithFormat(): void
    {
        $serializer = $this->getContainer()->get(JobsAdapterSerializer::class);

        $ref = new \ReflectionProperty($serializer, 'format');

        $this->assertNull($ref->getValue($serializer));
        $this->assertSame('serializer', $ref->getValue($serializer->withFormat('serializer')));
        $this->assertSame('json', $ref->getValue($serializer->withFormat('json')));
        $this->assertNull($ref->getValue($serializer->withFormat()));
    }
}
