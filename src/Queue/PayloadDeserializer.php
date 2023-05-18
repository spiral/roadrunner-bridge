<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\HandlerInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\Queue\SerializerRegistryInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;

final class PayloadDeserializer
{
    public function __construct(
        private readonly HandlerRegistryInterface $registry,
        private readonly SerializerRegistryInterface $serializer,
    ) {
    }

    /**
     * @throws JobsException
     * @throws \ReflectionException
     */
    public function deserialize(ReceivedTaskInterface $task): mixed
    {
        $payload = $task->getPayload();

        $serializer = $this->serializer->getSerializer($name = $task->getName());

        $class = $this->getClassFromHeader($task);
        if ($class !== null && \class_exists($class)) {
            return $serializer->unserialize($payload, $class);
        }

        $class = $this->detectTypeFromJobHandler(
            $this->registry->getHandler($name)
        );

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
     * @throws \ReflectionException
     *
     * @return class-string|null
     */
    private function detectTypeFromJobHandler(HandlerInterface $handler): ?string
    {
        $handler = new \ReflectionClass($handler);
        if (!$handler->hasMethod('invoke')) {
            return null;
        }

        $handlerMethod = $handler->getMethod('invoke');

        foreach ($handlerMethod->getParameters() as $parameter) {
            if ($parameter->getName() !== 'payload') {
                continue;
            }

            if ($parameter->getType() === null) {
                return null;
            }

            if ($parameter->getType() instanceof \ReflectionUnionType) {
                foreach ($parameter->getType()->getTypes() as $type) {
                    if ($type->isBuiltin()) {
                        continue;
                    }

                    return $type->getName();
                }

                return null;
            } else if($parameter->getType() instanceof \ReflectionNamedType) {
                if ($parameter->getType()->isBuiltin()) {
                    return null;
                }

                return $parameter->getType()->getName();
            }
        }

        return null;
    }
}
