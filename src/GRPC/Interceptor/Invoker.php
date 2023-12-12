<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Interceptor;

use Google\Protobuf\Internal\Message;
use Spiral\Core\CoreInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Exception\InvokeException;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\GRPC\StatusCode;

/**
 * @internal
 */
final class Invoker implements InvokerInterface
{
    public function __construct(
        private readonly CoreInterface $core,
    ) {
    }

    public function invoke(ServiceInterface $service, Method $method, ContextInterface $ctx, ?string $input): string
    {
        return $this->core->callAction($service::class, $method->name, [
            'service' => $service,
            'method' => $method,
            'ctx' => $ctx,
            'input' => $input,
            'message' => $this->makeInput($method, $input),
        ]);
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
