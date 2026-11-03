<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Client;

use Spiral\Core\ContainerScope;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\RoadRunnerBridge\GRPC\Internal\RpcSpanAttributes;
use Spiral\Telemetry\NullTracer;
use Spiral\Telemetry\SpanInterface;
use Spiral\Telemetry\TraceKind;
use Spiral\Telemetry\TracerInterface;

/**
 * Wraps an outgoing gRPC call in a `CLIENT` span and sends the trace context in the call metadata.
 *
 * The bridge puts it first among the common client interceptors, so a call retried by `RetryInterceptor`
 * is one span. Listed explicitly after `RetryInterceptor`, it makes a span per attempt instead.
 *
 * @api
 */
final class TracingInterceptor implements InterceptorInterface
{
    /**
     * Positions of the call arguments built by spiral/grpc-client.
     * Its `Helper::getMetadata()` reads the message in 1.0.0-rc2, so the metadata is read here directly.
     */
    private const ARG_METHOD = 1;

    private const ARG_METADATA = 4;

    #[\Override]
    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        $arguments = $context->getArguments();
        /** @var non-empty-string $method */
        $method = $arguments[self::ARG_METHOD];
        $tracer = self::tracer();

        return $tracer->trace(
            name: RpcSpanAttributes::clientMethod($method),
            callback: static function (SpanInterface $span) use ($context, $handler, $tracer, $arguments): mixed {
                # Only inside the span the tracer context names it as the parent of the remote one.
                $metadata = (array) ($arguments[self::ARG_METADATA] ?? []);
                foreach ($tracer->getContext() as $key => $value) {
                    $metadata[$key] = \is_array($value) ? \array_values($value) : [$value];
                }
                $arguments[self::ARG_METADATA] = $metadata;

                try {
                    $result = $handler->handle($context->withArguments($arguments));
                } catch (\Throwable $e) {
                    RpcSpanAttributes::applyClientFailure($span, $e);
                    throw $e;
                }

                RpcSpanAttributes::applySuccess($span);

                return $result;
            },
            attributes: RpcSpanAttributes::clientRequest($method),
            traceKind: TraceKind::CLIENT,
        );
    }

    /**
     * The tracer of the current scope, so the span joins the trace of the request that makes the call.
     */
    private static function tracer(): TracerInterface
    {
        try {
            $tracer = ContainerScope::getContainer()?->get(TracerInterface::class);
        } catch (\Throwable) {
            $tracer = null;
        }

        return $tracer instanceof TracerInterface ? $tracer : new NullTracer();
    }
}
