<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue\Internal;

use Psr\Log\LoggerInterface;
use RoadRunner\Lock\LockInterface;
use Spiral\Goridge\Exception\GoridgeException;
use Spiral\Goridge\RPC\Exception\CodecException;
use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\KafkaOptions;
use Spiral\RoadRunner\Jobs\KafkaOptionsInterface;
use Spiral\RoadRunner\Jobs\OptionsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\Queue\Driver;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunnerBridge\Config\QueueConfig;
use Spiral\RoadRunnerBridge\Queue\PipelineRegistryInterface;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

/**
 * Declares the pipelines of the `pipelines` config in RoadRunner.
 *
 * One process at a time declares a pipeline, under a RoadRunner lock; the others wait for it and connect.
 * RoadRunner declares a pipeline paused, and a `consume` one is resumed by its declarer. An existing pipeline
 * is never resumed, so one paused with `rr:jobs:pause` stays paused across worker restarts.
 *
 * @internal
 *
 * @psalm-import-type TPipeline from \Spiral\RoadRunnerBridge\Config\QueueConfig
 */
final class RPCPipelineRegistry implements PipelineRegistryInterface
{
    /** How long a declaration may hold its lock, in seconds: a lock left by a killed process expires. */
    private const LOCK_TTL = 20;

    /** How long to wait for another process declaring the same pipeline, in seconds. */
    private const LOCK_WAIT = 10;

    private int $expiresAt = 0;

    /** @var list<array-key> */
    private array $existPipelines = [];

    /** @var array<non-empty-string,TPipeline> */
    private readonly array $pipelines;

    /** @var non-empty-string */
    private readonly string $lockPrefix;

    /** @var array<string, non-empty-list<non-empty-string>>|null RoadRunner pipeline name => `pipelines` keys */
    private ?array $keys = null;

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
        $this->lockPrefix = $config->getLockPrefix();
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
                if (!$this->isExists($connector)) {
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
        $options = OptionsFactory::create($this->pipelines[$key]['options'] ?? null);
        if ($connector->getDriver() === Driver::Kafka && $options !== null && !$options instanceof KafkaOptionsInterface) {
            throw new InvalidArgumentException(\sprintf(
                'Pipeline `%s` has a Kafka connector: its `options` must be `%s` with the topic to push to.',
                $key,
                KafkaOptions::class,
            ));
        }

        $options = OptionsFactory::fromCreateInfo($connector, $options);

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
     * Declares the pipeline, and resumes a `consume` one, under the declaration lock; connects instead when
     * another process has declared it meanwhile.
     *
     * @throws JobsException
     */
    private function declare(CreateInfoInterface $connector, ?OptionsInterface $options = null): QueueInterface
    {
        $resource = $this->lockPrefix . 'declare:' . $connector->getName();
        try {
            $id = $this->lock->lock($resource, null, self::LOCK_TTL, self::LOCK_WAIT);
        } catch (GoridgeException|CodecException $e) {
            throw new JobsException(
                'RoadRunner lock call failed, check that the RoadRunner build includes the `lock` plugin: '
                . $e->getMessage(),
                $e->getCode(),
                $e,
            );
        }

        if ($id === false) {
            throw new JobsException(
                \sprintf('Pipeline "%s" is still being declared by another process.', $connector->getName()),
            );
        }

        try {
            $this->expiresAt = 0;
            if ($this->isExists($connector)) {
                return $this->connect($connector, $options);
            }

            try {
                /** @psalm-suppress TooManyArguments JobsInterface lacks the options Jobs accepts */
                $queue = $this->jobs->create($connector, $options);
            } catch (JobsException $e) {
                return $this->connectDeclaredElsewhere($connector, $options, $e);
            }

            $this->existPipelines[] = $connector->getName();

            if ($this->consumes($connector)) {
                $this->resume($queue, $connector);
            }

            return $queue;
        } finally {
            $this->release($resource, $id);
        }
    }

    /**
     * Connects to the pipeline after its creation failed, when a process outside the declaration lock created
     * it first; otherwise rethrows the creation error.
     *
     * @throws JobsException
     */
    private function connectDeclaredElsewhere(
        CreateInfoInterface $connector,
        ?OptionsInterface $options,
        JobsException $error,
    ): QueueInterface {
        $this->expiresAt = 0;
        try {
            $exists = $this->isExists($connector);
        } catch (JobsException) {
            throw $error;
        }

        if (!$exists) {
            throw $error;
        }

        # Normally the lock keeps other processes out; reaching here means one ignored it.
        $this->logger->warning(\sprintf(
            'Pipeline "%s" was declared by a process outside the declaration lock: another bridge version or `lockPrefix`, or a declaration that outlived the lock.',
            $connector->getName(),
        ));

        return $this->connect($connector, $options);
    }

    private function resume(QueueInterface $queue, CreateInfoInterface $connector): void
    {
        try {
            $queue->resume();
        } catch (JobsException $e) {
            $this->logger->warning(
                \sprintf('Unable to resume pipeline "%s". Reason: %s', $connector->getName(), $e->getMessage()),
            );
        }
    }

    /**
     * The lock expires anyway, so a failed release is logged rather than failing a done declaration or
     * hiding its error.
     *
     * @param non-empty-string $resource
     */
    private function release(string $resource, string $id): void
    {
        \assert($id !== '');

        try {
            $released = $this->lock->release($resource, $id);
        } catch (GoridgeException|CodecException $e) {
            $this->logger->warning(
                \sprintf('Unable to release the lock "%s". Reason: %s', $resource, $e->getMessage()),
            );

            return;
        }

        if (!$released) {
            $this->logger->warning(\sprintf(
                'The lock "%s" expired before the declaration ended, so another process may have declared the pipeline too.',
                $resource,
            ));
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
