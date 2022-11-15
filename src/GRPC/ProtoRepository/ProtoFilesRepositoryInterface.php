<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\ProtoRepository;

interface ProtoFilesRepositoryInterface
{
    /**
     * @return iterable<non-empty-string>
     */
    public function getProtos(): iterable;
}
