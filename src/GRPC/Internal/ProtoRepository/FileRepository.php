<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal\ProtoRepository;

use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;

/**
 * @internal
 */
final readonly class FileRepository implements ProtoFilesRepositoryInterface
{
    public function __construct(
        private array $protoFiles,
    ) {}

    #[\Override]
    public function getProtos(): iterable
    {
        yield from $this->protoFiles;
    }
}
