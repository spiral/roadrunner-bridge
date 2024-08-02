<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Psr\Log\LoggerInterface;
use Spiral\Queue\Exception\InvalidArgumentException;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunner\Jobs\JobsInterface;
use Spiral\RoadRunner\Jobs\OptionsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunnerBridge\Config\QueueConfig;
use Spiral\RoadRunnerBridge\RoadRunnerMode;

/**
 * @internal
 *
 * @psalm-import-type TPipeline from \Spiral\RoadRunnerBridge\Config\QueueConfig
 */
final class RPCPipelineRegistry implements PipelineRegistryInterface
{
    private int $expiresAt = 0;
    private array $existPipelines = [];
    /** @var array<non-empty-string,TPipeline> */
    private readonly array $pipelines;

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

            if (!$this->isExists($connector)) {
                try {
                    $this->jobs->create($connector)->resume();
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
    }

    /**
     * @throws JobsException
     * @throws InvalidArgumentException
     */
    public function getPipeline(string $name): QueueInterface
    {
        if (!isset($this->pipelines[$name])) {
            return $this->jobs->connect($name);
        }

        $connector = $this->getConnector($name);

        /**
         * @var OptionsInterface|null $options
         */
        $options = OptionsFactory::create($this->pipelines[$name]['options'] ?? null)
            ?? OptionsFactory::fromCreateInfo($connector);

        \assert($options === null || $options instanceof OptionsInterface);

        if (!$this->isExists($connector)) {
            return $this->create($connector, $options);
        }

        return $this->connect($connector, $options);
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
     * Create a new RoadRunner jobs pipeline
     *
     * @throws JobsException
     */
    private function create(CreateInfoInterface $connector, ?OptionsInterface $options = null): QueueInterface
    {
        $this->expiresAt = 0;
        /** @psalm-suppress TooManyArguments */
        return $this->jobs->create($connector, $options);
    }

    /**
     * Connect to the RoadRunner jobs pipeline
     */
    private function connect(CreateInfoInterface $connector, ?OptionsInterface $options = null): QueueInterface
    {
        /** @psalm-suppress TooManyArguments */
        return $this->jobs->connect($connector->getName(), $options);
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
                \sprintf('You must specify connector for given pipeline `%s`.', $name)
            );
        }

        if (!$this->pipelines[$name]['connector'] instanceof CreateInfoInterface) {
            throw new InvalidArgumentException(
                \sprintf('Connector should implement %s interface.', CreateInfoInterface::class)
            );
        }

        return $this->pipelines[$name]['connector'];
    }
}
