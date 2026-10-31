<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\RoadRunnerBridge\GRPC\Internal\FileRepository;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class FileRepositoryTest extends TestCase
{
    #[Test]
    public function getProtos(): void
    {
        $repository = new FileRepository($arr = ['foo', 'bar']);

        Assert::same(\iterator_to_array($repository->getProtos(), false), $arr);
    }
}
