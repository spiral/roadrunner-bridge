<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\GRPC;

use Spiral\Tests\ConsoleTestCase;

final class GenerateCommandTest extends ConsoleTestCase
{
    public function testGenerateServices(): void
    {
        $result = $this->generateGRPCService();

        $files = [
            'GRPC/Ping/PingServiceInterface.php',
            'GRPC/Ping/GPBMetadata/Service.php',
            'GRPC/Ping/PingRequest.php',
            'GRPC/Ping/PingResponse.php',
        ];

        $path = $this->getDirectoryByAlias('app') . 'proto/service.proto';
        $this->assertStringContainsString(
            \sprintf('Compiling `%s`:', \realpath($path) ?: $path),
            $result,
        );

        foreach ($files as $file) {
            $this->assertFileExists($this->getDirectoryByAlias('app') . $file);
            $this->assertStringContainsString(
                $file,
                $result,
            );
        }

        $this->assertMatchesRegularExpression(
            '#Proto file `.+proto[\\\\/]foo\\.proto` not found.#',
            $result,
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->deleteGRPCService();
    }
}
