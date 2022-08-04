<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\Options as QueueOptions;
use Spiral\RoadRunner\Jobs\Task\ProvidesHeadersInterface;
use Spiral\RoadRunner\Jobs\Task\WritableHeadersTrait;

final class Options extends QueueOptions implements ProvidesHeadersInterface, OptionsInterface
{
    use WritableHeadersTrait;
}
