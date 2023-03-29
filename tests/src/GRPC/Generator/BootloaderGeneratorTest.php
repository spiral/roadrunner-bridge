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
        $appPath = \dirname(__DIR__, 3) . '/app/GRPC/Generator';

        $bootloader = $appPath . '/Bootloader/ServiceBootloader.php';
        $expected = $appPath . '/Bootloader/ExpectedBootloader.php';
        $interface = $appPath . '/UsersServiceInterface.php';

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
            ->willReturn(\str_replace("\r\n", "\n", (new Files())->read($interface)));

        $files
            ->expects($this->once())
            ->method('write')
            ->with($bootloader, \str_replace("\r\n", "\n", (new Files())->read($expected)), null, true);

        $generator = new BootloaderGenerator($files);

        $generator->run([$interface], $appPath, 'GRPC');
    }
}
