<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Mockery as m;
use Spiral\RoadRunnerBridge\GRPC\Internal\CompositeRepository;
use Spiral\RoadRunnerBridge\GRPC\Internal\FileRepository;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class CompositeProtoFilesRepositoryTest extends TestCase
{
    #[Test]
    public function getProtos(): void
    {
        $repository = new CompositeRepository(
            $fooRepository = m::mock(ProtoFilesRepositoryInterface::class),
            new FileRepository(['path3', 'path4']),
        );

        $fooRepository->shouldReceive('getProtos')->withNoArgs()->andReturn(['path1', 'path2']);

        Assert::same(\iterator_to_array($repository->getProtos(), false), ['path1', 'path2', 'path3', 'path4']);
    }
}
