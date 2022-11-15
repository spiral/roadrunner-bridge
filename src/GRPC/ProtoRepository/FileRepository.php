<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\ProtoRepository;

class FileRepository implements ProtoFilesRepositoryInterface
{
    public function __construct(private readonly array $protoFiles)
    {
    }

    /**
     * @return array<non-empty-string>
     */
    public function getProtos(): iterable
    {
        return $this->protoFiles;
    }
}
