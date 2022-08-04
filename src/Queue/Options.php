<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Queue;

use Spiral\Queue\Options as QueueOptions;
use Spiral\RoadRunner\Jobs\Task\WritableHeadersInterface;
use Spiral\RoadRunner\Jobs\Task\WritableHeadersTrait;

final class Options extends QueueOptions implements WritableHeadersInterface, OptionsInterface
{
    use WritableHeadersTrait;
}
