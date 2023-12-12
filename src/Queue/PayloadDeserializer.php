<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\HandlerInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\SerializerRegistryInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

final class PayloadDeserializer implements PayloadDeserializerInterface
{
    /**
     * Cache for detected types.
     *
     * @var array<class-string, class-string|string|null>
     */
    private array $handlerTypes = [];

    public function __construct(
        private readonly HandlerRegistryInterface $registry,
        private readonly SerializerRegistryInterface $serializer,
    ) {
    }

    public function deserialize(ReceivedTaskInterface $task): mixed
    {
        $payload = $task->getPayload();

        $serializer = $this->serializer->getSerializer($name = $task->getName());

        $class = $this->getClassFromHeader($task);
        if ($class !== null && \class_exists($class)) {
            return $serializer->unserialize($payload, $class);
        }

        $class = $this->detectTypeFromJobHandler(
            $this->registry->getHandler($name),
        );

        if ($class === 'string') {
            return $payload;
        }

        if ($class !== null && \class_exists($class)) {
            return $serializer->unserialize($payload, $class);
        }

        return $serializer->unserialize($payload);
    }

    private function getClassFromHeader(ReceivedTaskInterface $task): ?string
    {
        if ($task->hasHeader(Queue::SERIALIZED_CLASS_HEADER_KEY)) {
            return $task->getHeaderLine(Queue::SERIALIZED_CLASS_HEADER_KEY);
        }

        return null;
    }

    /**
     * Detects the type of for payload argument of the given handler's method.
     *
     * @throws \ReflectionException
     *
     * @return class-string|string|null
     */
    private function detectTypeFromJobHandler(HandlerInterface $handler): ?string
    {
        $handler = new \ReflectionClass($handler);

        if (isset($this->handlerTypes[$handler->getName()])) {
            return $this->handlerTypes[$handler->getName()];
        }

        if (!$handler->hasMethod('invoke')) {
            return $this->handlerTypes[$handler->getName()] = null;
        }

        $handlerMethod = $handler->getMethod('invoke');

        foreach ($handlerMethod->getParameters() as $parameter) {
            if ($parameter->getName() !== 'payload') {
                continue;
            }

            $type = $this->detectType($parameter->getType());
            if ($type !== null) {
                return $this->handlerTypes[$handler->getName()] = $type;
            }
        }

        return $this->handlerTypes[$handler->getName()] = null;
    }

    /**
     * Detects the type of the given parameter.
     *
     * @throws \ReflectionException
     */
    private function detectType(\ReflectionType|null $type): ?string
    {
        if ($type instanceof \ReflectionNamedType) {
            if ($type->isBuiltin()) {
                return $type->getName() === 'string' ? 'string' : null;
            }

            return $type->getName();
        }

        if ($type instanceof \ReflectionUnionType) {
            foreach ($type->getTypes() as $t) {
                $class = $this->detectType($t);
                if ($class !== null) {
                    return $class;
                }
            }
        }

        return null;
    }
}
