<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\ProtoRepository;

final class CompositeRepository implements ProtoFilesRepositoryInterface
{
    private readonly array $repositories;

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
