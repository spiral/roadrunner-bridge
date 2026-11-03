<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue\Internal;

use Spiral\Queue\ExtendedOptionsInterface;
use Spiral\Queue\OptionsInterface;
use Spiral\RoadRunner\Jobs\KafkaOptions;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\OptionsInterface as JobsOptionsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\Queue\Driver;
use Spiral\RoadRunner\Jobs\Queue\KafkaCreateInfo;
use Spiral\RoadRunnerBridge\Queue\Options as BridgeOptions;

/**
 * @internal
 */
final class OptionsFactory
{
    public static function create(OptionsInterface|JobsOptionsInterface|null $options = null): ?JobsOptionsInterface
    {
        return match (true) {
            $options instanceof OptionsInterface => self::fromQueueOptions($options),
            default => $options,
        };
    }

    public static function fromQueueOptions(OptionsInterface $from): JobsOptionsInterface
    {
        $options = new Options($from->getDelay() ?? JobsOptionsInterface::DEFAULT_DELAY);
        if ($from instanceof ExtendedOptionsInterface) {
            /** @var array<non-empty-string>|non-empty-string $values */
            foreach ($from->getHeaders() as $header => $values) {
                $options = $options->withHeader($header, $values);
            }
        }

        if ($from instanceof BridgeOptions) {
            ($priority = $from->getPriority()) === null or $options = $options->withPriority($priority);
            ($autoAck = $from->getAutoAck()) === null or $options = $options->withAutoAck($autoAck);
        }

        return $options;
    }

    /**
     * A Kafka push needs `KafkaOptions`, and a per-push topic only survives the merge into them: a Kafka
     * pipeline without default options gets them for the single topic its connector consumes, or `default`.
     */
    public static function fromCreateInfo(
        CreateInfoInterface $connector,
        ?JobsOptionsInterface $options = null,
    ): ?JobsOptionsInterface {
        if ($options !== null || $connector->getDriver() !== Driver::Kafka) {
            return $options;
        }

        return new KafkaOptions(self::consumedTopic($connector) ?? 'default');
    }

    /**
     * @return non-empty-string|null
     */
    private static function consumedTopic(CreateInfoInterface $connector): ?string
    {
        $consumer = $connector instanceof KafkaCreateInfo ? $connector->consumerOptions : null;
        if ($consumer === null || $consumer->consumeRegexp) {
            return null;
        }

        $topics = $consumer->topics;
        foreach ($consumer->consumePartitions as $partition) {
            $topics[] = $partition->topic;
        }

        $topics = \array_values(\array_unique($topics));

        return \count($topics) === 1 ? $topics[0] : null;
    }
}
