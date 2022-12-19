<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\Generator;

use Spiral\Files\Files;
use Spiral\Files\FilesInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\ServiceClientGenerator;
use Spiral\Tests\TestCase;

final class ServiceClientGeneratorTest extends TestCase
{
    public function testRun(): void
    {
        $interface = \dirname(__DIR__, 3) . '/app/GRPC/Generator/UsersServiceInterface.php';
        $client = \dirname(__DIR__, 3) . '/app/GRPC/Generator/UsersServiceClient.php';
        $expected = \dirname(__DIR__, 3) . '/app/GRPC/Generator/ExpectedServiceClient.php';

        $files = $this->createMock(FilesInterface::class);
        $files
            ->expects($this->once())
            ->method('read')
            ->with($interface)
            ->willReturn((new Files())->read($interface));

        $files
            ->expects($this->once())
            ->method('write')
            ->with($client, (new Files())->read($expected), null, true);

        $generator = new ServiceClientGenerator($files);

        $generator->run([$interface], 'test', 'test');
    }
}
