<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\ExtendedOptionsInterface;
use Spiral\Queue\OptionsInterface;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\OptionsInterface as JobsOptionsInterface;

/**
 * @internal
 */
final class OptionsFactory
{
    public static function create(OptionsInterface|JobsOptionsInterface|null $options = null): ?JobsOptionsInterface
    {
        return match (true) {
            $options instanceof OptionsInterface => self::fromQueueOptions($options),
            default => $options
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
}
