<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal;

/**
 * The `buf.gen.yaml` template for a run: either a user-owned file or one rendered from local binaries.
 *
 * @internal
 * @psalm-immutable
 */
final readonly class BufTemplate
{
    private function __construct(
        public ?string $file,
        private string $protocPath = '',
        private string $pluginPath = '',
    ) {}

    /**
     * A user-owned template, used as is: its plugins decide where the files go.
     *
     * @psalm-pure
     */
    public static function user(string $file): self
    {
        return new self($file);
    }

    /**
     * A template that runs the protoc built-in PHP generator and the local `protoc-gen-php-grpc` plugin.
     *
     * @psalm-pure
     */
    public static function local(string $protocPath, string $pluginPath): self
    {
        return new self(null, $protocPath, $pluginPath);
    }

    /**
     * Renders the local template with both plugins writing into one directory.
     *
     * Managed mode stays off: it would replace `php_namespace` options declared in the proto files.
     *
     * @return non-empty-string JSON, which buf reads as YAML.
     */
    public function render(string $outDir): string
    {
        return \json_encode(
            [
                'version' => 'v2',
                'plugins' => [
                    ['protoc_builtin' => 'php', 'protoc_path' => $this->protocPath, 'out' => $outDir],
                    ['local' => $this->pluginPath, 'out' => $outDir],
                ],
            ],
            \JSON_UNESCAPED_SLASHES | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR,
        );
    }
}
