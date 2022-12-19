<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\Generator;

use Spiral\Files\Files;
use Spiral\Files\FilesInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\ConfigGenerator;
use Spiral\Tests\TestCase;

final class ConfigGeneratorTest extends TestCase
{
    public function testRun(): void
    {
        $config = \dirname(__DIR__, 3) . '/app/GRPC/Generator/Config/GRPCServicesConfig.php';
        $expected = \dirname(__DIR__, 3) . '/app/GRPC/Generator/Config/ExpectedConfig.php';

        $files = $this->createMock(FilesInterface::class);
        $files
            ->expects($this->once())
            ->method('exists')
            ->with($config)
            ->willReturn(false);

        $files
            ->expects($this->once())
            ->method('write')
            ->with($config, (new Files())->read($expected), null, true);

        $generator = new ConfigGenerator($files);

        $generator->run([], \dirname(__DIR__, 3) . '/app/GRPC/Generator', 'GRPC');
    }

    public function testRunWithExistenceConfig(): void
    {
        $config = \dirname(__DIR__, 3) . '/app/GRPC/Generator/Config/GRPCServicesConfig.php';

        $files = $this->createMock(FilesInterface::class);
        $files
            ->expects($this->once())
            ->method('exists')
            ->with($config)
            ->willReturn(true);

        $files->expects($this->never())->method('write');

        $generator = new ConfigGenerator($files);

        $generator->run([], \dirname(__DIR__, 3) . '/app/GRPC/Generator', 'GRPC');
    }
}
