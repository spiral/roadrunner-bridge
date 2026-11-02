<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue\Internal;

use Psr\Log\LoggerInterface;
use RoadRunner\Lock\LockInterface;
use Spiral\Goridge\Exception\GoridgeException;
use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\OptionsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunnerBridge\Config\QueueConfig;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

/**
 * Declares the pipelines of the `pipelines` config in RoadRunner.
 *
 * One process at a time declares a pipeline, under a RoadRunner lock; the others wait for it. A pipeline is
 * declared paused, and a `consume` one is resumed by its declarer. A pending marker lives from the declaration
 * until that resume, so a pipeline whose declarer died in between is resumed by the next process, while a
 * pipeline paused on purpose (`rr:jobs:pause`) stays paused.
 *
 * @internal
 *
 * @psalm-import-type TPipeline from \Spiral\RoadRunnerBridge\Config\QueueConfig
 */
final class RPCPipelineRegistry implements PipelineRegistryInterface
{
    /** How long a declaration may hold its lock, in seconds. */
    private const DECLARE_TTL = 30;

    /** How long to wait for another process declaring the same pipeline, in seconds. */
    private const DECLARE_WAIT = 10;

    private int $expiresAt = 0;

    /** @var list<array-key> */
    private array $existPipelines = [];

    /** @var array<non-empty-string,TPipeline> */
    private readonly array $pipelines;

    /** @var array<string, non-empty-list<non-empty-string>>|null RoadRunner pipeline name => `pipelines` keys */
    private ?array $keys = null;

    private readonly PipelineLocks $locks;

    /**
     * @param Jobs|JobsInterface $jobs
     * @param int $ttl Time to cache existing RoadRunner pipelines
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JobsInterface $jobs,
        private readonly RoadRunnerMode $mode,
        private readonly LockInterface $lock,
        QueueConfig $config,
        private readonly int $ttl = 60,
    ) {
        $this->pipelines = $config->getPipelines();
        $this->locks = new PipelineLocks($config);
    }

    /**
     * @throws JobsException
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function declareConsumerPipelines(): void
    {
        if ($this->mode !== RoadRunnerMode::Jobs) {
            return;
        }

        $this->expiresAt = 0;

        foreach ($this->pipelines as $name => $pipeline) {
            $consume = $pipeline['consume'] ?? false;
            if (!$consume) {
                continue;
            }

            $connector = $this->getConnector($name);

            try {
                if (!$this->isExists($connector) || $this->isPending($connector)) {
                    $this->declare($connector);
                }
            } catch (JobsException $e) {
                $this->logger->warning(
                    \sprintf(
                        'Unable to declare consumer pipeline "%s". Reason: %s',
                        $name,
                        $e->getMessage(),
                    ),
                );
            }
        }
    }

    /**
     * Takes a `pipelines` key or the RoadRunner name of a configured pipeline; any other name connects
     * to a pipeline declared in RoadRunner itself.
     *
     * @throws JobsException
     * @throws InvalidArgumentException
     */
    #[\Override]
    public function getPipeline(string $name): QueueInterface
    {
        $key = isset($this->pipelines[$name]) ? $name : $this->keyOf($name);
        if ($key === null) {
            return $this->jobs->connect($name);
        }

        $connector = $this->getConnector($key);
        $options = OptionsFactory::fromCreateInfo(
            $connector,
            OptionsFactory::create($this->pipelines[$key]['options'] ?? null),
        );

        if (!$this->isExists($connector)) {
            return $this->declare($connector, $options);
        }

        return $this->connect($connector, $options);
    }

    /**
     * @param non-empty-string $name
     *
     * @throws InvalidArgumentException
     */
    public function getConnector(string $name): CreateInfoInterface
    {
        // Connector is required for pipeline declaration
        if (!isset($this->pipelines[$name]['connector'])) {
            throw new InvalidArgumentException(
                \sprintf('You must specify connector for given pipeline `%s`.', $name),
            );
        }

        if (!$this->pipelines[$name]['connector'] instanceof CreateInfoInterface) {
            throw new InvalidArgumentException(
                \sprintf('Connector should implement %s interface.', CreateInfoInterface::class),
            );
        }

        return $this->pipelines[$name]['connector'];
    }

    /**
     * @return non-empty-string|null The `pipelines` key whose connector declares the RoadRunner pipeline.
     */
    private function keyOf(string $pipeline): ?string
    {
        $this->keys ??= $this->connectorKeys();

        return $this->keys[$pipeline][0] ?? null;
    }

    /**
     * Whether any `pipelines` entry declaring the RoadRunner pipeline asks to consume it.
     */
    private function consumes(CreateInfoInterface $connector): bool
    {
        $this->keys ??= $this->connectorKeys();

        foreach ($this->keys[$connector->getName()] ?? [] as $key) {
            if ($this->pipelines[$key]['consume'] ?? false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, non-empty-list<non-empty-string>> RoadRunner pipeline name => `pipelines` keys
     */
    private function connectorKeys(): array
    {
        $keys = [];
        foreach ($this->pipelines as $key => $config) {
            $connector = $config['connector'] ?? null;
            if ($connector instanceof CreateInfoInterface) {
                $keys[$connector->getName()][] = $key;
            }
        }

        return $keys;
    }

    /**
     * Check if RoadRunner jobs pipeline exists
     */
    private function isExists(CreateInfoInterface $connector): bool
    {
        if ($this->expiresAt < \time()) {
            $this->existPipelines = \array_keys(
                \iterator_to_array($this->jobs->getIterator()),
            );

            $this->expiresAt = \time() + $this->ttl;
        }

        return \in_array($connector->getName(), $this->existPipelines, true);
    }

    /**
     * Declares the pipeline unless it exists and resumes a `consume` pipeline left pending, holding the
     * declaration lock meanwhile.
     *
     * @throws JobsException
     */
    private function declare(CreateInfoInterface $connector, ?OptionsInterface $options = null): QueueInterface
    {
        $pipeline = $connector->getName();
        $resource = $this->locks->declare($pipeline);
        $id = $this->call(fn(): string|false => $this->lock->lock($resource, null, self::DECLARE_TTL, self::DECLARE_WAIT));
        if ($id === false) {
            throw new JobsException(
                \sprintf('Pipeline "%s" is still being declared by another process.', $pipeline),
            );
        }

        try {
            $this->expiresAt = 0;
            $consume = $this->consumes($connector);
            if ($this->isExists($connector)) {
                $queue = $this->connect($connector, $options);
            } else {
                if ($consume) {
                    $this->call(fn(): string|false => $this->lock->lock($this->locks->pending($pipeline)));
                }

                try {
                    $queue = $this->create($connector, $options);
                } catch (JobsException $e) {
                    # A marker left without a pipeline would resume one later declared and paused elsewhere.
                    if ($consume) {
                        $this->releaseQuietly($this->locks->pending($pipeline));
                    }

                    throw $e;
                }
            }

            if ($consume && $this->isPending($connector)) {
                $this->resume($queue, $connector);
            }

            return $queue;
        } finally {
            # The lock expires anyway; a failed release must not fail a done declaration or hide its error.
            $this->releaseQuietly($resource, $id);
        }
    }

    /**
     * Creates the pipeline, or connects to it when a process outside the declaration lock created it first.
     *
     * @throws JobsException
     */
    private function create(CreateInfoInterface $connector, ?OptionsInterface $options): QueueInterface
    {
        try {
            /** @psalm-suppress TooManyArguments JobsInterface lacks the options Jobs accepts */
            $queue = $this->jobs->create($connector, $options);
        } catch (JobsException $e) {
            $this->expiresAt = 0;
            try {
                $exists = $this->isExists($connector);
            } catch (JobsException) {
                throw $e;
            }

            if (!$exists) {
                throw $e;
            }

            return $this->connect($connector, $options);
        }

        $this->existPipelines[] = $connector->getName();

        return $queue;
    }

    /**
     * A failed resume keeps the pending marker, so the next process declaring the pipeline retries it.
     */
    private function resume(QueueInterface $queue, CreateInfoInterface $connector): void
    {
        try {
            $queue->resume();
        } catch (JobsException $e) {
            $this->logger->warning(
                \sprintf('Unable to resume pipeline "%s". Reason: %s', $connector->getName(), $e->getMessage()),
            );

            return;
        }

        $this->call(fn(): bool => $this->lock->forceRelease($this->locks->pending($connector->getName())));
    }

    /**
     * Releases a lock, or any holder's lock without an id, logging an RPC failure instead of throwing it.
     */
    private function releaseQuietly(string $resource, ?string $id = null): void
    {
        \assert($resource !== '' && $id !== '');

        try {
            $id === null ? $this->lock->forceRelease($resource) : $this->lock->release($resource, $id);
        } catch (GoridgeException $e) {
            $this->logger->warning(
                \sprintf('Unable to release the lock "%s". Reason: %s', $resource, $e->getMessage()),
            );
        }
    }

    /**
     * @throws JobsException
     */
    private function isPending(CreateInfoInterface $connector): bool
    {
        return $this->call(fn(): bool => $this->lock->exists($this->locks->pending($connector->getName())));
    }

    /**
     * Runs a lock call, reporting an RPC failure as a jobs failure.
     *
     * @template T
     *
     * @param \Closure(): T $call
     *
     * @return T
     * @throws JobsException
     */
    private function call(\Closure $call): mixed
    {
        try {
            return $call();
        } catch (GoridgeException $e) {
            throw new JobsException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Connect to the RoadRunner jobs pipeline
     */
    private function connect(CreateInfoInterface $connector, ?OptionsInterface $options = null): QueueInterface
    {
        /** @psalm-suppress TooManyArguments JobsInterface lacks the options Jobs accepts */
        return $this->jobs->connect($connector->getName(), $options);
    }
}
