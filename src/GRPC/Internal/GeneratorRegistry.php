<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal;

use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistryInterface;

/**
 * @internal
 */
final class GeneratorRegistry implements GeneratorRegistryInterface
{
    /**
     * @var GeneratorInterface[]
     */
    private array $generators = [];

    #[\Override]
    public function addGenerator(GeneratorInterface $generator): void
    {
        if (!$this->hasGenerator($generator::class)) {
            $this->generators[] = $generator;
        }
    }

    /**
     * @return GeneratorInterface[]
     */
    #[\Override]
    public function getGenerators(): array
    {
        return $this->generators;
    }

    public function hasGenerator(string $name): bool
    {
        foreach ($this->generators as $generator) {
            if ($generator::class === $name) {
                return true;
            }
        }

        return false;
    }
}
