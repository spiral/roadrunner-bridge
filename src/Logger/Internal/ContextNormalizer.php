<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Logger\Internal;

use Monolog\Formatter\NormalizerFormatter;

/**
 * Normalizes record context with Monolog's rules (depth and item limits, NaN/INF, exceptions with
 * their `previous` chain) and cuts exception traces to a fixed number of frames.
 *
 * @internal
 */
final class ContextNormalizer extends NormalizerFormatter
{
    /**
     * @param positive-int $traceCount
     */
    public function __construct(
        private readonly int $traceCount = 5,
    ) {
        parent::__construct();
    }

    /**
     * `setMaxTraceLength()` would do the same, but it appeared only in Monolog 3.11.
     */
    #[\Override]
    protected function normalizeException(\Throwable $e, int $depth = 0): array
    {
        $normalized = parent::normalizeException($e, $depth);

        if (isset($normalized['trace']) && \is_array($normalized['trace'])) {
            $normalized['trace'] = \array_slice($normalized['trace'], 0, $this->traceCount);
        }

        return $normalized;
    }
}
