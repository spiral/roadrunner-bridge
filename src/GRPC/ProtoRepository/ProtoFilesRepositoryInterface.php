<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\ProtoRepository;

interface ProtoFilesRepositoryInterface
{
    /**
     * Get proto files paths
     *
     * @return iterable<non-empty-string>
     */
    public function getProtos(): iterable;
}
