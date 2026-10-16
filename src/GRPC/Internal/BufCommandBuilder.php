<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal;

/**
 * Builds the `buf generate` argument list.
 *
 * @internal
 * @psalm-immutable
 */
final readonly class BufCommandBuilder
{
    public function __construct(
        private string $bufPath,
    ) {}

    /**
     * @param string $input Input directory, the root that imports are resolved from.
     * @param string $template Path to the template file.
     * @param list<string> $paths Directories inside the input to limit generation to; empty means the whole input.
     *
     * @return non-empty-list<string>
     */
    public function build(string $input, string $template, array $paths = []): array
    {
        $command = [$this->bufPath, 'generate', $input, '--template', $template];
        foreach ($paths as $path) {
            $command[] = '--path';
            $command[] = $path;
        }

        return $command;
    }
}
