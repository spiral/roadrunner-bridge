<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Interceptor;

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
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\GRPC\StatusCode;
use Spiral\RoadRunnerBridge\GRPC\UnaryCall;
use Spiral\RoadRunnerBridge\GRPC\UnaryCallInterface;

/**
 * @internal
 */
final class Invoker implements InvokerInterface
{
    public function __construct(
        private readonly HandlerInterface $handler,
        #[Proxy] private readonly ScopeInterface $scope,
    ) {}

    public function invoke(ServiceInterface $service, Method $method, ContextInterface $ctx, ?string $input): string
    {
        $message = $this->makeInput($method, $input);
        $handler = $this->handler;

        /** @psalm-suppress InvalidArgument */
        return $this->scope->runScope(
            new Scope('grpc-request', [UnaryCallInterface::class => new UnaryCall($ctx, $method, $message)]),
            static fn(): string => self::resultToString($handler->handle(
                new CallContext(Target::fromPair($service, $method->name), [
                    $ctx,
                    $message,
                ]),
            )),
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
    private function makeInput(Method $method, ?string $body): Message
    {
        try {
            $class = $method->inputType;

            /** @psalm-suppress UnsafeInstantiation */
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
