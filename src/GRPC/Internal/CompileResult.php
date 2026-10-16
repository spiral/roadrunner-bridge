<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal;

/**
 * @internal
 * @psalm-immutable
 */
final readonly class CompileResult
{
    /**
     * @param list<string> $files Full paths of the generated files.
     * @param string $warnings Error output of the successful buf run.
     */
    public function __construct(
        public array $files,
        public string $warnings = '',
    ) {}
}
