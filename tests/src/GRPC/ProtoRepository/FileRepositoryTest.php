<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\ProtoRepository;

use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\FileRepository;
use Spiral\Tests\TestCase;

class FileRepositoryTest extends TestCase
{
    public function testGetProtos(): void
    {
        $repository = new FileRepository($arr = ['foo', 'bar']);

        $this->assertSame($arr, iterator_to_array($repository->getProtos(), false));
    }
}
