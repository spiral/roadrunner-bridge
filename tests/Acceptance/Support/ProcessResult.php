<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\Support;

final class ProcessResult
{
    /**
     * @param string $output Standard output and error output, interleaved.
     */
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output,
    ) {}
}
