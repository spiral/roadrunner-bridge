<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Queue\HandlerRegistryInterface;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Spiral\Queue\Failed\FailedJobHandlerInterface;

final class Dispatcher implements DispatcherInterface
{
    private EnvironmentInterface $env;
    private ContainerInterface $container;
    private FinalizerInterface $finalizer;

    public function __construct(
        ContainerInterface $container,
        FinalizerInterface $finalizer,
        EnvironmentInterface $env
    ) {
        $this->env = $env;
        $this->container = $container;
        $this->finalizer = $finalizer;
    }

    public function canServe(): bool
    {
        return \PHP_SAPI == 'cli' && $this->env->getMode() === Mode::MODE_JOBS;
    }

    /**
     * @return mixed
     * @throws \Spiral\RoadRunner\Jobs\Exception\JobsException
     */
    public function serve()
    {
        /** @var ConsumerInterface $consumer */
        $consumer = $this->container->get(ConsumerInterface::class);

        /** @var HandlerRegistryInterface $handlerRegistry */
        $handlerRegistry = $this->container->get(HandlerRegistryInterface::class);

        while ($task = $consumer->waitTask()) {
            try {
                $instance = $handlerRegistry->getHandler($task->getName());

                $instance->handle($task->getName(), $task->getId(), $task->getPayload());
                $task->complete();
            } catch (\Throwable $e) {
                $this->handleException($task, $e);
                $task->fail($e);
            }

            $this->finalizer->finalize(false);
        }
    }

    protected function handleException(?ReceivedTaskInterface $task, \Throwable $e): void
    {
        try {
            $this->container->get(FailedJobHandlerInterface::class)->handle(
                'roadrunner',
                $task->getQueue(),
                $task->getName(),
                $task->getPayload(),
                $e
            );
        } catch (\Throwable|ContainerExceptionInterface $se) {
            // no need to notify when unable to register an exception
        }
    }
}
