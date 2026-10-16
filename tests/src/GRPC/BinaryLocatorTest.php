<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use PHPUnit\Framework\TestCase;
use Spiral\RoadRunnerBridge\GRPC\Exception\BinaryNotFoundException;
use Spiral\RoadRunnerBridge\GRPC\Internal\BinaryLocator;

final class BinaryLocatorTest extends TestCase
{
    private string $dir;

    public function testConfiguredPathWins(): void
    {
        $configured = $this->touch('custom/buf');
        $this->touch('root/buf');

        $locator = new BinaryLocator($this->dir . '/root', '', false);

        $this->assertSame($configured, $locator->locate('buf', 'bufPath', $configured));
    }

    public function testMissingConfiguredPathIsNotSilentlyReplaced(): void
    {
        $this->touch('root/buf');
        $locator = new BinaryLocator($this->dir . '/root', '', false);

        $this->expectException(BinaryNotFoundException::class);
        $this->expectExceptionMessage('`bufPath`');

        $locator->locate('buf', 'bufPath', $this->dir . '/missing/buf');
    }

    public function testRootDirectory(): void
    {
        $this->touch('root/protoc');
        $this->touch('path/protoc');

        $locator = new BinaryLocator($this->dir . '/root', $this->dir . '/path', false);

        $this->assertSame(
            $this->dir . '/root' . \DIRECTORY_SEPARATOR . 'protoc',
            $locator->locate('protoc', 'protocPath', null),
        );
    }

    public function testWindowsExtension(): void
    {
        $this->touch('root/protoc');
        $this->touch('root/protoc.exe');

        $locator = new BinaryLocator($this->dir . '/root', '', true);

        $this->assertSame(
            $this->dir . '/root' . \DIRECTORY_SEPARATOR . 'protoc.exe',
            $locator->locate('protoc', 'protocPath', ''),
        );
    }

    public function testPathEnvironment(): void
    {
        $this->touch('path2/protoc-gen-php-grpc');

        $locator = new BinaryLocator(
            $this->dir . '/root',
            \implode(\PATH_SEPARATOR, ['', $this->dir . '/path1', $this->dir . '/path2']),
            false,
        );

        $this->assertSame(
            $this->dir . '/path2' . \DIRECTORY_SEPARATOR . 'protoc-gen-php-grpc',
            $locator->locate('protoc-gen-php-grpc', 'pluginPath', null),
        );
    }

    public function testNotFoundPointsToDload(): void
    {
        $locator = new BinaryLocator($this->dir . '/root', $this->dir . '/path', false);

        $this->expectException(BinaryNotFoundException::class);
        $this->expectExceptionMessage('vendor/bin/dload get protoc-gen-php-grpc');

        $locator->locate('protoc-gen-php-grpc', 'pluginPath', null);
    }

    protected function setUp(): void
    {
        $this->dir = \sys_get_temp_dir() . '/spiral-binary-locator-' . \bin2hex(\random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (!\is_dir($this->dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? \rmdir($file->getPathname()) : \unlink($file->getPathname());
        }

        \rmdir($this->dir);
    }

    private function touch(string $path): string
    {
        $path = $this->dir . '/' . $path;
        \is_dir(\dirname($path)) or \mkdir(\dirname($path), 0777, true);
        \touch($path);

        return $path;
    }
}
