<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Generator;

interface GeneratorRegistryInterface
{
    public function addGenerator(GeneratorInterface $generator): void;

    /**
     * @return GeneratorInterface[]
     */
    public function getGenerators(): array;
}
