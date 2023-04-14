<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Queue\Exception\RetryException;
use Spiral\Queue\ExtendedOptionsInterface;
use Spiral\Queue\Interceptor\Consume\Handler;
use Spiral\Queue\OptionsInterface;
use Spiral\Queue\SerializerRegistryInterface;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\OptionsInterface as JobsOptionsInterface;
use Spiral\RoadRunner\Jobs\Task\ProvidesHeadersInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

final class Dispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly FinalizerInterface $finalizer,
        private readonly RoadRunnerMode $mode,
    ) {
    }

    public function canServe(): bool
    {
        return \PHP_SAPI === 'cli' && $this->mode === RoadRunnerMode::Jobs;
    }

    /**
     * @throws JobsException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function serve(): void
    {
        /** @var ConsumerInterface $consumer */
        $consumer = $this->container->get(ConsumerInterface::class);

        /** @var SerializerRegistryInterface $serializer */
        $serializer = $this->container->get(SerializerRegistryInterface::class);

        /** @var Handler $handler */
        $handler = $this->container->get(Handler::class);

        while ($task = $consumer->waitTask()) {
            try {
                $handler->handle(
                    name: $task->getName(),
                    driver: 'roadrunner',
                    queue: $task->getQueue(),
                    id: $task->getId(),
                    payload: $this->deserializePayload($serializer, $task),
                    headers: $task->getHeaders(),
                );

                $task->complete();
            } catch (RetryException $e) {
                $this->retry($e, $task);
            } catch (\Throwable $e) {
                $task->fail($e);
            }

            $this->finalizer->finalize(terminate: false);
        }
    }

    public function retry(RetryException $e, ReceivedTaskInterface $task): void
    {
        $options = $e->getOptions();

        if ($options instanceof ProvidesHeadersInterface || $options instanceof ExtendedOptionsInterface) {
            /** @var array<non-empty-string>|non-empty-string $values */
            foreach ($options->getHeaders() as $header => $values) {
                $task = $task->withHeader($header, $values);
            }
        }
        if (
            ($options instanceof OptionsInterface || $options instanceof JobsOptionsInterface) &&
            ($delay = $options->getDelay()) !== null
        ) {
            $task = $task->withDelay($delay);
        }

        $task->fail($e, true);
    }

    private function deserializePayload(SerializerRegistryInterface $serializer, ReceivedTaskInterface $task): mixed
    {
        $payload = $task->getPayload();

        $serializer = $serializer->getSerializer($task->getName());

        if (
            $task->hasHeader(Queue::SERIALIZED_CLASS_HEADER_KEY)
            && \class_exists($class = $task->getHeaderLine(Queue::SERIALIZED_CLASS_HEADER_KEY))
        ) {
            return $serializer->unserialize($payload, $class);
        }

        return $serializer->unserialize($payload);
    }
}
