<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue;

use Spiral\App\Job\TestJob;
use Spiral\Queue\Job\ObjectJob;
use Spiral\RoadRunnerBridge\Queue\JobsAdapterSerializer;
use Spiral\Serializer\Serializer\JsonSerializer;
use Spiral\Serializer\Serializer\PhpSerializer;
use Spiral\Tests\TestCase;

final class JobsAdapterSerializerTest extends TestCase
{
    public function testJobSerializer(): void
    {
        $serializer = $this->getContainer()->get(JobsAdapterSerializer::class);

        $ref = new \ReflectionProperty($serializer, 'serializer');

        $this->assertInstanceOf(JsonSerializer::class, $ref->getValue($serializer));
        $this->assertInstanceOf(PhpSerializer::class, $ref->getValue($serializer->changeSerializer(TestJob::class)));
        $this->assertInstanceOf(JsonSerializer::class, $ref->getValue($serializer->changeSerializer(ObjectJob::class)));
    }
}
