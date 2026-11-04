<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal;

use Spiral\Grpc\Client\Exception\ServiceClientException;
use Spiral\Grpc\Client\Exception\TimeoutException;
use Spiral\RoadRunner\GRPC\Exception\GRPCExceptionInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\Telemetry\SpanInterface;

/**
 * Builds span names and attributes that follow the OpenTelemetry gRPC semantic conventions.
 *
 * @see https://opentelemetry.io/docs/specs/semconv/rpc/grpc/
 *
 * @internal
 */
final class RpcSpanAttributes
{
    private const STATUS_NAMES = [
        0 => 'OK',
        1 => 'CANCELLED',
        2 => 'UNKNOWN',
        3 => 'INVALID_ARGUMENT',
        4 => 'DEADLINE_EXCEEDED',
        5 => 'NOT_FOUND',
        6 => 'ALREADY_EXISTS',
        7 => 'PERMISSION_DENIED',
        8 => 'RESOURCE_EXHAUSTED',
        9 => 'FAILED_PRECONDITION',
        10 => 'ABORTED',
        11 => 'OUT_OF_RANGE',
        12 => 'UNIMPLEMENTED',
        13 => 'INTERNAL',
        14 => 'UNAVAILABLE',
        15 => 'DATA_LOSS',
        16 => 'UNAUTHENTICATED',
    ];

    /**
     * @return non-empty-string The fully-qualified method, such as `ping.dto.PingService/Ping`.
     */
    public static function method(ServiceInterface $service, Method $method): string
    {
        # `NAME` is declared by generated service interfaces, not by ServiceInterface itself.
        $constant = $service::class . '::NAME';
        $name = \defined($constant) ? (string) \constant($constant) : $service::class;

        return $name . '/' . $method->name;
    }

    /**
     * @return array<non-empty-string, string>
     */
    public static function serverRequest(ServiceInterface $service, Method $method): array
    {
        return [
            'rpc.system.name' => 'grpc',
            'rpc.method' => self::method($service, $method),
        ];
    }

    public static function applySuccess(SpanInterface $span): void
    {
        $span->setAttribute('rpc.response.status_code', self::STATUS_NAMES[0]);
    }

    /**
     * Every status other than `OK` is an error, on the server too: the call ends with an exception, and
     * spiral/otel-bridge exports a span whose callback throws as an error anyway.
     */
    public static function applyFailure(SpanInterface $span, \Throwable $e): void
    {
        $status = match (true) {
            $e instanceof GRPCExceptionInterface,
            $e instanceof ServiceClientException => self::STATUS_NAMES[$e->getCode()] ?? 'UNKNOWN',
            $e instanceof TimeoutException => 'DEADLINE_EXCEEDED',
            # A server worker fails without a gRPC status, and RoadRunner picks the code the client sees.
            default => null,
        };

        $status === null or $span->setAttribute('rpc.response.status_code', $status);
        $span
            ->setAttribute('error.type', $status ?? $e::class)
            ->setStatus('ERROR');
    }

    /**
     * @param non-empty-string $path The method as the client sends it: `/ping.dto.PingService/Ping`.
     *
     * @return non-empty-string The fully-qualified method, such as `ping.dto.PingService/Ping`.
     */
    public static function clientMethod(string $path): string
    {
        /** @var non-empty-string The service and the method names follow the slash */
        return \ltrim($path, '/');
    }

    /**
     * @param non-empty-string $path The method as the client sends it: `/ping.dto.PingService/Ping`.
     *
     * @return array<non-empty-string, string>
     */
    public static function clientRequest(string $path): array
    {
        return [
            'rpc.system.name' => 'grpc',
            'rpc.method' => self::clientMethod($path),
        ];
    }
}
