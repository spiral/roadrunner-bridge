<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\Generator;

use Spiral\Files\Files;
use Spiral\Files\FilesInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\BootloaderGenerator;
use Spiral\Tests\TestCase;

final class BootloaderGeneratorTest extends TestCase
{
    public function testRun(): void
    {
        $bootloader = \dirname(__DIR__, 3) . '/app/GRPC/Generator/Bootloader/ServiceBootloader.php';
        $expected = \dirname(__DIR__, 3) . '/app/GRPC/Generator/Bootloader/ExpectedBootloader.php';
        $interface = \dirname(__DIR__, 3) . '/app/GRPC/Generator/UsersServiceInterface.php';

        $files = $this->createMock(FilesInterface::class);
        $files
            ->expects($this->once())
            ->method('exists')
            ->with($bootloader)
            ->willReturn(false);

        $files
            ->expects($this->exactly(2))
            ->method('read')
            ->with($interface)
            ->willReturn((new Files())->read($interface));

        $files
            ->expects($this->once())
            ->method('write')
            ->with($bootloader, (new Files())->read($expected), null, true);

        $generator = new BootloaderGenerator($files);

        $generator->run([$interface], \dirname(__DIR__, 3) . '/app/GRPC/Generator', 'GRPC');
    }
}
