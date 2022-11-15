<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\ProtoRepository;

use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\CompositeProtoFilesRepository;
use Mockery as m;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;
use Spiral\Tests\TestCase;

class CompositeProtoFilesRepositoryTest extends TestCase
{
    public function testGetProtos(): void
    {
        $repository = new CompositeProtoFilesRepository(
            $fooRepository = m::mock(ProtoFilesRepositoryInterface::class),
            $barRepository = m::mock(ProtoFilesRepositoryInterface::class)
        );

        $fooRepository->shouldReceive('getProtos')->withNoArgs()->andReturn(['test1', 'test2']);
        $barRepository->shouldReceive('getProtos')->withNoArgs()->andReturn(['test3', 'test4']);

        $this->assertSame(['test1', 'test2', 'test3', 'test4'], iterator_to_array($repository->getProtos(), false));
    }
}
