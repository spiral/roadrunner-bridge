<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC\Stub;

use Spiral\Telemetry\Span;
use Spiral\Telemetry\TraceKind;
use Spiral\Telemetry\TracerFactoryInterface;
use Spiral\Telemetry\TracerInterface;

/**
 * Runs the callback with a plain span and keeps what the code under test gave the tracer.
 */
final class RecordingTracer implements TracerInterface, TracerFactoryInterface
{
    public array $incoming = [];
    public ?string $name = null;
    public ?TraceKind $kind = null;
    public ?Span $span = null;

    /**
     * @param array<string, string|list<string>> $context Context the tracer hands back for propagation.
     */
    public function __construct(
        private readonly array $context = [],
    ) {}

    #[\Override]
    public function make(array $context = []): TracerInterface
    {
        $this->incoming = $context;
        return $this;
    }

    #[\Override]
    public function getContext(): array
    {
        return $this->context;
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
        $this->name = $name;
        $this->kind = $traceKind;
        $this->span = new Span($name, $attributes);

        return $callback($this->span);
    }
}
