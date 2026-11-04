<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal;

use Google\Protobuf\Internal\Message;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\HandlerInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Exception\InvokeException;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ResponseHeaders;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\GRPC\StatusCode;
use Spiral\RoadRunnerBridge\GRPC\UnaryCallInterface;
use Spiral\Telemetry\NullTracerFactory;
use Spiral\Telemetry\SpanInterface;
use Spiral\Telemetry\TraceKind;
use Spiral\Telemetry\TracerFactoryInterface;

/**
 * @internal
 */
final readonly class Invoker implements InvokerInterface
{
    private TracerFactoryInterface $tracerFactory;

    public function __construct(
        private HandlerInterface $handler,
        #[Proxy] private ScopeInterface $scope,
        ?TracerFactoryInterface $tracerFactory = null,
    ) {
        $this->tracerFactory = $tracerFactory ?? new NullTracerFactory($scope);
    }

    #[\Override]
    public function invoke(ServiceInterface $service, Method $method, ContextInterface $ctx, ?string $input): string
    {
        $tracer = $this->tracerFactory->make(self::metadata($ctx));
        $handler = $this->handler;
        $scope = $this->scope;

        try {
            /** @var string */
            return $tracer->trace(
                name: RpcSpanAttributes::method($service, $method),
                callback: static function (SpanInterface $span) use ($scope, $handler, $service, $method, $ctx, $input): string {
                    try {
                        $message = self::makeInput($method, $input);

                        /** @psalm-suppress InvalidArgument ScopeInterface lacks the Scope form the container accepts */
                        $result = $scope->runScope(
                            new Scope('grpc-request', [UnaryCallInterface::class => new UnaryCall($ctx, $method, $message)]),
                            static fn(): string => self::resultToString($handler->handle(
                                new CallContext(Target::fromPair($service, $method->name), [
                                    $ctx,
                                    $message,
                                ]),
                            )),
                        );
                    } catch (\Throwable $e) {
                        RpcSpanAttributes::applyServerFailure($span, $e);
                        throw $e;
                    }

                    RpcSpanAttributes::applySuccess($span);

                    return $result;
                },
                attributes: RpcSpanAttributes::serverRequest($service, $method),
                scoped: true,
                traceKind: TraceKind::SERVER,
            );
        } finally {
            # The server sends response headers with a gRPC error too, so the trace context reaches the caller either way.
            $headers = $ctx->getValue(ResponseHeaders::class);
            if ($headers instanceof ResponseHeaders) {
                /**
                 * @var non-empty-string $key
                 * @var string|list<string> $value
                 */
                foreach ($tracer->getContext() as $key => $value) {
                    $headers->set($key, \is_array($value) ? \implode(', ', $value) : $value);
                }
            }
        }
    }

    /**
     * Incoming metadata without the response holders the server puts into the same context.
     *
     * @return array<string, mixed>
     */
    private static function metadata(ContextInterface $ctx): array
    {
        return \array_filter(
            $ctx->getValues(),
            static fn(mixed $value): bool => \is_string($value) || \is_array($value),
        );
    }

    /**
     * Converts the result from the GRPC service method to the string.
     *
     * @throws InvokeException
     */
    private static function resultToString(Message $result): string
    {
        try {
            return $result->serializeToString();
        } catch (\Throwable $e) {
            throw InvokeException::create($e->getMessage(), StatusCode::INTERNAL, $e);
        }
    }

    /**
     * Converts the input from the GRPC service method to the Message object.
     *
     * @throws InvokeException
     */
    private static function makeInput(Method $method, ?string $body): Message
    {
        try {
            $class = $method->inputType;

            /** @psalm-suppress UnsafeInstantiation Generated protobuf messages share the Message constructor */
            $in = new $class();

            if ($body !== null) {
                $in->mergeFromString($body);
            }

            return $in;
        } catch (\Throwable $e) {
            throw InvokeException::create($e->getMessage(), StatusCode::INTERNAL, $e);
        }
    }
}
