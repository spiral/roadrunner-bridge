<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Scaffolder;

use Spiral\Files\FilesInterface;
use Spiral\Tests\TestCase;

abstract class AbstractCommandTest extends TestCase
{
    protected ?string $className = null;

    protected function deleteDeclaration(string $class): void
    {
        if (\class_exists($class)) {
            try {
                $reflection = new \ReflectionClass($class);
                $this->getContainer()->get(FilesInterface::class)->delete($reflection->getFileName());
            } catch (\Throwable $exception) {
                var_dump($exception->getMessage());
            }
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->className) {
            $this->deleteDeclaration($this->className);
        }
    }
}
