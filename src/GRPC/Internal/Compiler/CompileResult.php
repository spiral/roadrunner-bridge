<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal\Compiler;

/**
 * @internal
 * @psalm-immutable
 */
final readonly class CompileResult
{
    /**
     * @param list<string> $files Full paths of the generated files.
     * @param string $warnings Error output of the successful buf run.
     * @param list<string> $foreignNamespaces Top-level namespaces of generated classes outside the base namespace:
     *        they are placed by their full namespace, so the base namespace's autoload rule does not cover them.
     */
    public function __construct(
        public array $files,
        public string $warnings = '',
        public array $foreignNamespaces = [],
    ) {}
}
