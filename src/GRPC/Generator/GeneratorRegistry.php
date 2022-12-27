<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Generator;

final class GeneratorRegistry implements GeneratorRegistryInterface
{
    /**
     * @var GeneratorInterface[]
     */
    private array $generators = [];

    public function addGenerator(GeneratorInterface $generator): void
    {
        if (!$this->hasGenerator($generator::class)) {
            $this->generators[] = $generator;
        }
    }

    /**
     * @return GeneratorInterface[]
     */
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
