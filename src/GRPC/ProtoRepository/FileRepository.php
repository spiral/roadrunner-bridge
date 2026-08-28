<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\ProtoRepository;

/**
 * @internal
 */
final readonly class FileRepository implements ProtoFilesRepositoryInterface
{
    public function __construct(
        private array $protoFiles,
    ) {}

    public function getProtos(): iterable
    {
        yield from $this->protoFiles;
    }
}
