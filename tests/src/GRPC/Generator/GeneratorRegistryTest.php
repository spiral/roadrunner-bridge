<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\Generator;

use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistry;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class GeneratorRegistryTest extends TestCase
{
    #[Test]
    public function addGenerator(): void
    {
        $registry = new GeneratorRegistry();

        Assert::count($registry->getGenerators(), 0);

        $generator = \Mockery::mock(GeneratorInterface::class)->shouldIgnoreMissing();
        $registry->addGenerator($generator);
        $registry->addGenerator($generator);

        // not duplicated
        Assert::count($registry->getGenerators(), 1);

        Assert::same($registry->getGenerators(), [$generator]);
    }

    #[Test]
    public function getGenerators(): void
    {
        $registry = new GeneratorRegistry();

        Assert::count($registry->getGenerators(), 0);

        $generator = \Mockery::mock(GeneratorInterface::class)->shouldIgnoreMissing();
        $generator2 = new class implements GeneratorInterface {
            public function run(array $files, string $targetPath, string $namespace): void {}
        };

        $registry->addGenerator($generator);
        $registry->addGenerator($generator2);

        Assert::count($registry->getGenerators(), 2);

        Assert::same($registry->getGenerators(), [$generator, $generator2]);
    }

    #[Test]
    public function hasGenerator(): void
    {
        $registry = new GeneratorRegistry();
        $generator = \Mockery::mock(GeneratorInterface::class)->shouldIgnoreMissing();

        Assert::false($registry->hasGenerator($generator::class));

        $registry->addGenerator($generator);

        Assert::true($registry->hasGenerator($generator::class));
    }
}
