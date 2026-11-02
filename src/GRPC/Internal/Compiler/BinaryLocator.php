<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal\Compiler;

use Internal\Path;
use Spiral\RoadRunnerBridge\GRPC\Exception\BinaryNotFoundException;

/**
 * Finds an executable: the configured path, then `<root>/<name>[.exe]` where DLoad puts it, then `PATH`.
 *
 * @internal
 */
final readonly class BinaryLocator
{
    /**
     * @param string $rootDir Project root directory.
     * @param string|null $pathEnv Value of the `PATH` variable; `null` reads it from the environment.
     *
     * @psalm-capabilities read-props
     */
    public function __construct(
        private string $rootDir,
        private ?string $pathEnv = null,
        private bool $windows = \PHP_OS_FAMILY === 'Windows',
    ) {}

    /**
     * @param non-empty-string $name Binary name without extension; also the DLoad alias.
     * @param non-empty-string $configKey The gRPC config key that overrides the lookup.
     * @param Path|string|null $configured Value of that config key; empty means unset.
     *
     * @return non-empty-string Path to the executable.
     *
     * @throws BinaryNotFoundException
     */
    public function locate(string $name, string $configKey, Path|string|null $configured): string
    {
        $hint = \sprintf(
            'Download it with `vendor/bin/dload get %s` (add `<download software="%s"/>` to dload.xml) '
            . 'or set `%s` in the gRPC config.',
            $name,
            $name,
            $configKey,
        );

        if ($configured !== null && $configured !== '') {
            $configured = Path::create($configured);
            $configured->isFile() or throw new BinaryNotFoundException(
                \sprintf('The `%s` binary `%s` set in `%s` was not found. %s', $name, $configured, $configKey, $hint),
            );

            return (string) $configured;
        }

        $fileName = $name . ($this->windows ? '.exe' : '');

        $local = Path::create($this->rootDir)->join($fileName);
        if ($local->isFile()) {
            return (string) $local;
        }

        $pathEnv = $this->pathEnv ?? (string) \getenv('PATH');
        foreach (\explode(\PATH_SEPARATOR, $pathEnv) as $dir) {
            if ($dir === '') {
                continue;
            }

            $candidate = Path::create($dir)->join($fileName);
            if ($candidate->isFile()) {
                // A relative `PATH` entry resolves from the current directory, which is not where buf runs.
                // Not realpath(): version-manager shims are symlinks that pick the tool by their own name.
                return (string) $candidate->absolute();
            }
        }

        throw new BinaryNotFoundException(\sprintf('The `%s` binary was not found. %s', $name, $hint));
    }
}
