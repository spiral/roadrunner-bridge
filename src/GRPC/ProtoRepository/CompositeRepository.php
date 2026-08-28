<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\ProtoRepository;

/**
 * @internal
 */
final readonly class CompositeRepository implements ProtoFilesRepositoryInterface
{
    private array $repositories;

    public function __construct(ProtoFilesRepositoryInterface ...$repositories)
    {
        $this->repositories = $repositories;
    }

    public function getProtos(): iterable
    {
        foreach ($this->repositories as $repository) {
            yield from $repository->getProtos();
        }
    }
}
