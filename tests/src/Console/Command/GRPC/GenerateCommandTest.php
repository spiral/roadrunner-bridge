<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\GRPC;

use Spiral\Tests\ConsoleTestCase;

final class GenerateCommandTest extends ConsoleTestCase
{
    public function testGenerateServices()
    {
        $result = $this->generateGRPCService();

        $files = [
            'GRPC/EchoService/EchoInterface.php',
            'GRPC/EchoService/Message.php',
            'GRPC/EchoService/GPBMetadata/PBEcho.php',
        ];

        $this->assertStringContainsString(
            sprintf('Compiling `%s`:', $this->getDirectoryByAlias('app') . 'proto/echo.proto'),
            $result
        );

        foreach ($files as $file) {
            $this->assertFileExists($this->getDirectoryByAlias('app') . $file);
            $this->assertStringContainsString(
                $file,
                $result
            );
        }

        $this->assertStringContainsString(
            sprintf('Proto file `%s` not found.', $this->getDirectoryByAlias('app') . 'proto/foo.proto'),
            $result
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->deleteGRPCService();
    }
}
