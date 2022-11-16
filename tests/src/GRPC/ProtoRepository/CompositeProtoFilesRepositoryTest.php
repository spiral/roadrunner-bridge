<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\ProtoRepository;

use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\CompositeProtoFilesRepository;
use Mockery as m;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\FileRepository;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;
use Spiral\Tests\TestCase;

class CompositeProtoFilesRepositoryTest extends TestCase
{
    public function testGetProtos(): void
    {
        $repository = new CompositeProtoFilesRepository(
            $fooRepository = m::mock(ProtoFilesRepositoryInterface::class),
            new FileRepository(['path3', 'path4']),
        );

        $fooRepository->shouldReceive('getProtos')->withNoArgs()->andReturn(['path1', 'path2']);

        $this->assertSame(['path1', 'path2', 'path3', 'path4'], iterator_to_array($repository->getProtos(), false));
    }
}
