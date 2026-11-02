<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\Support;

use Spiral\Files\Files;

/**
 * An application root under `runtime/acceptance` with a copy of the proto fixtures and its own gRPC config,
 * for running `grpc:generate` of `tests/Acceptance/GenerateApp/console.php` in a separate process.
 */
final class GenerateProject
{
    public const APP_DIR = __DIR__ . '/../GenerateApp';

    private function __construct(
        public readonly string $root,
    ) {}

    /**
     * @param array<non-empty-string, mixed> $config Overrides of the default gRPC config.
     */
    public static function create(array $config = []): self
    {
        $root = \dirname(__DIR__, 3) . '/runtime/acceptance/' . \bin2hex(\random_bytes(4));
        $files = new Files();
        $project = new self($root);
        foreach (['proto', 'outside'] as $dir) {
            foreach ($files->getFiles(self::APP_DIR . '/' . $dir) as $file) {
                $project->write($files->relativePath($file, self::APP_DIR), $files->read($file));
            }
        }

        # The tokenizer scans `app` for console commands and fails on a missing directory.
        $files->ensureDirectory($root . '/app');

        $project->write('config/grpc.php', '<?php return ' . \var_export($config + [
            'bufPath' => self::binary('buf'),
            'protocPath' => self::binary('protoc'),
            'pluginPath' => self::binary('protoc-gen-php-grpc'),
            'generatedPath' => 'generated',
            'namespace' => 'Acceptance',
            'servicesBasePath' => 'proto',
            'services' => ['proto/shop/order.proto', 'proto/notes/note.proto'],
        ], true) . ';');

        return $project;
    }

    /**
     * A binary of the repository root, relative to the project root: relative config paths resolve from it.
     */
    public static function binary(string $name): string
    {
        return '../../../' . $name . (\PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    }

    /**
     * @param list<string> $args Script and its arguments.
     * @param array<non-empty-string, string> $env
     */
    public static function php(array $args, ?string $cwd = null, array $env = []): ProcessResult
    {
        # A file instead of pipes: reading one pipe to the end blocks while the process fills the other.
        $output = \tmpfile();
        $process = \proc_open(
            [\PHP_BINARY, ...$args],
            [0 => ['pipe', 'r'], 1 => $output, 2 => $output],
            $pipes,
            $cwd ?? \dirname(__DIR__, 3),
            \array_merge(\getenv(), $env),
        );
        \is_resource($process) or throw new \RuntimeException('Cannot start PHP.');
        \fclose($pipes[0]);
        $exitCode = \proc_close($process);

        \rewind($output);
        $result = new ProcessResult($exitCode, (string) \stream_get_contents($output));
        \fclose($output);

        return $result;
    }

    /**
     * @param list<string> $args
     * @param string|null $cwd Working directory of the process; null is the repository root.
     */
    public function generate(array $args = [], ?string $cwd = null): ProcessResult
    {
        return self::php([self::APP_DIR . '/console.php', 'grpc:generate', ...$args], $cwd, [
            'GENERATE_ROOT' => $this->root,
        ]);
    }

    public function write(string $path, string $content): void
    {
        (new Files())->write($this->root . '/' . $path, $content, ensureDirectory: true);
    }

    /**
     * @return list<string> Paths relative to the project root, with forward slashes, sorted.
     */
    public function files(string $dir): array
    {
        $files = new Files();
        if (!$files->isDirectory($this->root . '/' . $dir)) {
            return [];
        }

        $result = \array_map(
            fn(string $file): string => \str_replace('\\', '/', $files->relativePath($file, $this->root)),
            $files->getFiles($this->root . '/' . $dir),
        );
        \sort($result);

        return $result;
    }

    public function remove(): void
    {
        (new Files())->deleteDirectory($this->root);
    }
}
