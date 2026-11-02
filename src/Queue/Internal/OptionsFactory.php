<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue\Internal;

use Spiral\Queue\ExtendedOptionsInterface;
use Spiral\Queue\OptionsInterface;
use Spiral\RoadRunner\Jobs\KafkaOptions;
use Spiral\RoadRunner\Jobs\KafkaOptionsInterface;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\OptionsInterface as JobsOptionsInterface;
use Spiral\RoadRunner\Jobs\Queue\CreateInfoInterface;
use Spiral\RoadRunner\Jobs\Queue\Driver;
use Spiral\RoadRunner\Jobs\Task\ProvidesHeadersInterface;

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

        return $options;
    }

    /**
     * Adapts the pipeline's default options to its driver: a Kafka push needs `KafkaOptions` for its topic.
     */
    public static function fromCreateInfo(
        CreateInfoInterface $connector,
        ?JobsOptionsInterface $options = null,
    ): ?JobsOptionsInterface {
        if ($connector->getDriver() !== Driver::Kafka || $options instanceof KafkaOptionsInterface) {
            return $options;
        }

        if ($options === null) {
            return new KafkaOptions('default');
        }

        # KafkaOptions::from() takes the topic `default` and drops the headers.
        $kafka = KafkaOptions::from($options);
        if ($options instanceof ProvidesHeadersInterface) {
            /** @var array<non-empty-string> $values */
            foreach ($options->getHeaders() as $header => $values) {
                $kafka = $kafka->withHeader($header, $values);
            }
        }

        return $kafka;
    }
}
