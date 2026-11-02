<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\Path;
use Testo\Assert;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Test;

#[Test]
final class PathTest
{
    public static function paths(): iterable
    {
        yield 'POSIX root' => ['/', true];
        yield 'POSIX' => ['/usr/bin/buf', true];
        yield 'drive with a backslash' => ['C:\\tools\\buf.exe', true];
        yield 'drive with a slash' => ['C:/tools/buf.exe', true];
        yield 'lowercase drive' => ['d:\\buf.exe', true];
        yield 'UNC' => ['\\\\server\\share\\buf.exe', true];
        yield 'root of the current drive' => ['\\tools\\buf.exe', true];
        yield 'phar' => ['phar://app.phar/proto', true];
        yield 'file URL' => ['file:///proto', true];
        yield 'wrapper with digits and signs' => ['vfs1+a.b-c://proto', true];
        yield 'relative' => ['proto/service.proto', false];
        yield 'dot relative' => ['./proto', false];
        yield 'parent relative' => ['../proto', false];
        yield 'relative to the current directory of a drive' => ['C:proto', false];
        yield 'bare drive' => ['C:', false];
        yield 'wrapper without slashes' => ['phar:proto', false];
        yield 'empty' => ['', false];
    }

    #[DataProvider('paths')]
    public function isAbsolute(string $path, bool $absolute): void
    {
        Assert::same(Path::isAbsolute($path), $absolute);
    }

    #[DataProvider('paths')]
    public function fromKeepsAbsolutePaths(string $path, bool $absolute): void
    {
        Assert::same(Path::from('/app', $path) === $path, $absolute);
    }

    #[DataSet(['/app', 'proto'], 'POSIX base')]
    #[DataSet(['/app/', 'proto'], 'trailing slash')]
    #[DataSet(['/app\\', 'proto'], 'trailing backslash')]
    #[DataSet(['/app//', 'proto'], 'several trailing separators')]
    public function fromJoinsRelativePathWithBase(string $base, string $path): void
    {
        Assert::same(Path::from($base, $path), '/app' . \DIRECTORY_SEPARATOR . 'proto');
    }

    #[DataSet(['C:\\app\\', 'C:\\app'], 'Windows base')]
    #[DataSet(['/', ''], 'root base')]
    public function fromTrimsSeparatorsOfBase(string $base, string $trimmed): void
    {
        Assert::same(Path::from($base, 'proto/a.proto'), $trimmed . \DIRECTORY_SEPARATOR . 'proto/a.proto');
    }

    #[DataSet([null], 'null')]
    #[DataSet([''], 'empty string from an unset environment variable')]
    public function fromTreatsEmptyAsUnset(?string $path): void
    {
        Assert::null(Path::from('/app', $path));
    }
}
