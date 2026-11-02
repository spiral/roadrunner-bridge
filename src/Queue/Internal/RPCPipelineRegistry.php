<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue\Internal;

use Psr\Log\LoggerInterface;
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
 * @internal
 *
 * @psalm-import-type TPipeline from \Spiral\RoadRunnerBridge\Config\QueueConfig
 */
final class RPCPipelineRegistry implements PipelineRegistryInterface
{
    private int $expiresAt = 0;

    /** @var list<array-key> */
    private array $existPipelines = [];

    /** @var array<non-empty-string,TPipeline> */
    private readonly array $pipelines;

    /** @var array<string, non-empty-string>|null RoadRunner pipeline name => `pipelines` key */
    private ?array $keys = null;

    /**
     * @param Jobs|JobsInterface $jobs
     * @param int $ttl Time to cache existing RoadRunner pipelines
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly JobsInterface $jobs,
        private readonly RoadRunnerMode $mode,
        QueueConfig $config,
        private readonly int $ttl = 60,
    ) {
        $this->pipelines = $config->getPipelines();
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
            if ($this->isExists($connector)) {
                continue;
            }

            try {
                $this->create($name, $connector);
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
            return $this->create($key, $connector, $options);
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
        if ($this->keys === null) {
            $this->keys = [];
            foreach ($this->pipelines as $key => $config) {
                $connector = $config['connector'] ?? null;
                if ($connector instanceof CreateInfoInterface) {
                    $this->keys[$connector->getName()] ??= $key;
                }
            }
        }

        return $this->keys[$pipeline] ?? null;
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
     * Declares the pipeline in RoadRunner, or connects to it when another process declared it after the
     * cached list was read.
     *
     * @param non-empty-string $name The `pipelines` key.
     *
     * @throws JobsException
     */
    private function create(
        string $name,
        CreateInfoInterface $connector,
        ?OptionsInterface $options = null,
    ): QueueInterface {
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

        # RoadRunner declares a pipeline paused, and a booting jobs worker leaves an existing pipeline
        # alone (one paused by `rr:jobs:pause` stays paused): whoever declares a `consume` pipeline resumes it.
        if ($this->pipelines[$name]['consume'] ?? false) {
            try {
                $queue->resume();
            } catch (JobsException $e) {
                $this->logger->warning(
                    \sprintf('Unable to resume pipeline "%s". Reason: %s', $name, $e->getMessage()),
                );
            }
        }

        return $queue;
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
