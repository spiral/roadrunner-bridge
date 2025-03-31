<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue\Internal;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Spiral\Attribute\DispatcherScope;
use Spiral\Boot\DispatcherInterface;
use Spiral\Boot\FinalizerInterface;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\Queue\Exception\RetryException;
use Spiral\Queue\ExtendedOptionsInterface;
use Spiral\Queue\Interceptor\Consume\Handler;
use Spiral\Queue\OptionsInterface;
use Spiral\Queue\Task;
use Spiral\Queue\TaskInterface;
use Spiral\RoadRunner\Jobs\ConsumerInterface;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\OptionsInterface as JobsOptionsInterface;
use Spiral\RoadRunner\Jobs\Task\ProvidesHeadersInterface;
use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Spiral\RoadRunnerBridge\Queue\PayloadDeserializerInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

/**
 * @internal
 */
#[DispatcherScope(scope: 'queue')]
final class Dispatcher implements DispatcherInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly FinalizerInterface $finalizer,
        private readonly ScopeInterface $scope,
        private readonly ExceptionReporterInterface $reporter,
    ) {}

    public static function canServe(RoadRunnerMode $mode): bool
    {
        return \PHP_SAPI === 'cli' && $mode === RoadRunnerMode::Jobs;
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

        /** @var PayloadDeserializerInterface $deserializer */
        $deserializer = $this->container->get(PayloadDeserializerInterface::class);

        /** @var Handler $handler */
        $handler = $this->container->get(Handler::class);

        while ($task = $consumer->waitTask()) {
            try {
                /** @psalm-suppress InvalidArgument */
                $this->scope->runScope(
                    new Scope('queue-task', [TaskInterface::class => new Task(
                        id: $task->getId(),
                        queue: $task->getQueue(),
                        name: $task->getName(),
                        payload: $deserializer->deserialize($task),
                        headers: $task->getHeaders(),
                    )]),
                    static function (TaskInterface $queueTask) use ($handler, $task): void {
                        $handler->handle(
                            name: $queueTask->getName(),
                            driver: 'roadrunner',
                            queue: $queueTask->getQueue(),
                            id: $queueTask->getId(),
                            payload: $queueTask->getPayload(),
                            headers: $queueTask->getHeaders(),
                        );

                        $task->complete();
                    },
                );
            } catch (RetryException $e) {
                $this->reporter->report($e);
                $this->retry($e, $task);
                unset($e);
            } catch (\Throwable $e) {
                $this->reporter->report($e);
                $task->fail($e);
                unset($e);
            }

            $this->finalizer->finalize(terminate: false);
        }
    }

    /**
     * @throws JobsException
     */
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
}
