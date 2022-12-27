<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\Generator;

use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistry;
use Spiral\Tests\TestCase;

final class GeneratorRegistryTest extends TestCase
{
    public function testAddGenerator(): void
    {
        $registry = new GeneratorRegistry();

        $this->assertCount(0, $registry->getGenerators());

        $generator = $this->createMock(GeneratorInterface::class);
        $registry->addGenerator($generator);
        $registry->addGenerator($generator);

        // not duplicated
        $this->assertCount(1, $registry->getGenerators());

        $this->assertSame([$generator], $registry->getGenerators());
    }

    public function testGetGenerators(): void
    {
        $registry = new GeneratorRegistry();

        $this->assertCount(0, $registry->getGenerators());

        $generator = $this->createMock(GeneratorInterface::class);
        $generator2 = new class () implements GeneratorInterface {
            public function run(array $files, string $targetPath, string $namespace): void
            {
            }
        };

        $registry->addGenerator($generator);
        $registry->addGenerator($generator2);

        $this->assertCount(2, $registry->getGenerators());

        $this->assertSame([$generator, $generator2], $registry->getGenerators());
    }

    public function testHasGenerator(): void
    {
        $registry = new GeneratorRegistry();
        $generator = $this->createMock(GeneratorInterface::class);

        $this->assertFalse($registry->hasGenerator($generator::class));

        $registry->addGenerator($generator);

        $this->assertTrue($registry->hasGenerator($generator::class));
    }
}
