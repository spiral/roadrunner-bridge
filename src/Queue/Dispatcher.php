<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Psr\Container\ContainerInterface;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Queue\Exception\RetryException;
use Spiral\Queue\Interceptor\Consume\Handler;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Task\ProvidesHeadersInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

final class Dispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly FinalizerInterface $finalizer,
        private readonly RoadRunnerMode $mode
    ) {
    }

    public function canServe(): bool
    {
        return \PHP_SAPI === 'cli' && $this->mode === RoadRunnerMode::Jobs;
    }

    /**
     * @throws JobsException
     */
    public function serve(): void
    {
        /** @var ConsumerInterface $consumer */
        $consumer = $this->container->get(ConsumerInterface::class);

        /** @var Handler $handler */
        $handler = $this->container->get(Handler::class);

        while ($task = $consumer->waitTask()) {
            try {
                $handler->handle(
                    name: $task->getName(),
                    driver: 'roadrunner',
                    queue: $task->getQueue(),
                    id: $task->getId(),
                    payload: $task->getPayload()
                );

                $task->complete();
            } catch (RetryException $e) {
                $options = $e->getOptions();
                if ($options instanceof ProvidesHeadersInterface) {
                    /** @var non-empty-string|array<non-empty-string> $values */
                    foreach ($options->getHeaders() as $header => $values) {
                        $task = $task->withHeader($header, $values);
                    }
                }
                if ($options instanceof OptionsInterface) {
                    $task = $task->withDelay($options->getDelay());
                }

                $task->fail($e, true);
            } catch (\Throwable $e) {
                $task->fail($e);
            }

            $this->finalizer->finalize(false);
        }
    }
}
