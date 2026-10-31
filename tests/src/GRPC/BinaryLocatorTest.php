<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\RoadRunnerBridge\GRPC\Exception\BinaryNotFoundException;
use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\BinaryLocator;
use Testo\Assert;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

#[Test]
final class BinaryLocatorTest
{
    private string $dir;

    public function configuredPathWins(): void
    {
        $configured = $this->touch('custom/buf');
        $this->touch('root/buf');

        $locator = new BinaryLocator($this->dir . '/root', '', false);

        Assert::same($locator->locate('buf', 'bufPath', $configured), $configured);
    }

    public function missingConfiguredPathIsNotSilentlyReplaced(): void
    {
        $this->touch('root/buf');
        $locator = new BinaryLocator($this->dir . '/root', '', false);

        Expect::exception(BinaryNotFoundException::class)->withMessageContaining('`bufPath`');

        $locator->locate('buf', 'bufPath', $this->dir . '/missing/buf');
    }

    public function rootDirectory(): void
    {
        $this->touch('root/protoc');
        $this->touch('path/protoc');

        $locator = new BinaryLocator($this->dir . '/root', $this->dir . '/path', false);

        Assert::same($locator->locate('protoc', 'protocPath', null), $this->dir . '/root' . \DIRECTORY_SEPARATOR . 'protoc');
    }

    public function windowsExtension(): void
    {
        $this->touch('root/protoc');
        $this->touch('root/protoc.exe');

        $locator = new BinaryLocator($this->dir . '/root', '', true);

        Assert::same($locator->locate('protoc', 'protocPath', ''), $this->dir . '/root' . \DIRECTORY_SEPARATOR . 'protoc.exe');
    }

    public function pathEnvironment(): void
    {
        $this->touch('path2/protoc-gen-php-grpc');

        $locator = new BinaryLocator(
            $this->dir . '/root',
            \implode(\PATH_SEPARATOR, ['', $this->dir . '/path1', $this->dir . '/path2']),
            false,
        );

        Assert::same($locator->locate('protoc-gen-php-grpc', 'pluginPath', null), $this->dir . '/path2' . \DIRECTORY_SEPARATOR . 'protoc-gen-php-grpc');
    }

    public function notFoundPointsToDload(): void
    {
        $locator = new BinaryLocator($this->dir . '/root', $this->dir . '/path', false);

        Expect::exception(BinaryNotFoundException::class)->withMessageContaining('vendor/bin/dload get protoc-gen-php-grpc');

        $locator->locate('protoc-gen-php-grpc', 'pluginPath', null);
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->dir = \sys_get_temp_dir() . '/spiral-binary-locator-' . \bin2hex(\random_bytes(4));
    }

    #[AfterTest]
    protected function cleanUp(): void
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
