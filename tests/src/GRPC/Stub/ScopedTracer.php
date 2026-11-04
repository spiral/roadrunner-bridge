<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\Stub;

use Spiral\Telemetry\AbstractTracer;
use Spiral\Telemetry\Span;
use Spiral\Telemetry\TraceKind;
use Spiral\Telemetry\TracerFactoryInterface;
use Spiral\Telemetry\TracerInterface;

/**
 * Runs the callback through the scope the framework tracers open, and names its context after the innermost
 * running span, as a real tracer does.
 */
final class ScopedTracer extends AbstractTracer implements TracerFactoryInterface
{
    /** @var list<string> */
    public array $spans = [];

    /** @var list<string> */
    private array $running = [];

    #[\Override]
    public function make(array $context = []): TracerInterface
    {
        return $this;
    }

    #[\Override]
    public function getContext(): array
    {
        return $this->running === [] ? [] : ['traceparent' => \end($this->running)];
    }

    #[\Override]
    public function trace(
        string $name,
        callable $callback,
        array $attributes = [],
        bool $scoped = false,
        ?TraceKind $traceKind = null,
        ?int $startTime = null,
    ): mixed {
        $this->spans[] = $name;
        $this->running[] = $name;

        try {
            return $this->runScope(new Span($name, $attributes), $callback);
        } finally {
            \array_pop($this->running);
        }
    }
}
