<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal\Compiler;

/**
 * @internal
 */
final class Path
{
    /**
     * Whether the path is absolute: `/`, `\`, a drive (`C:\`, `C:/`) or a stream wrapper (`phar://`).
     *
     * @psalm-pure
     */
    public static function isAbsolute(string $path): bool
    {
        return \preg_match('#^(?:[a-z]:)?[\\\\/]|^[a-z][a-z0-9+.-]*://#i', $path) === 1;
    }

    /**
     * The path resolved from the base directory, unless it is absolute. An empty path means unset, as a config
     * value read from an unset environment variable.
     *
     * @return non-empty-string|null
     *
     * @psalm-pure
     */
    public static function from(string $base, ?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (self::isAbsolute($path)) {
            return $path;
        }

        return \rtrim($base, '\\/') . \DIRECTORY_SEPARATOR . $path;
    }
}
