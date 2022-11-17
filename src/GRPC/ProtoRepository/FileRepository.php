<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\ProtoRepository;

final class FileRepository implements ProtoFilesRepositoryInterface
{
    public function __construct(
        private readonly array $protoFiles
    ) {
    }

    public function getProtos(): iterable
    {
        yield from $this->protoFiles;
    }
}
