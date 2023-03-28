<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Command;
use Spiral\Console\Console;

/**
 * @deprecated Will be removed in the next major release of RoadRunner Bridge (version 3.0)
 */
final class DeprecatedListCommand extends Command
{
    protected const SIGNATURE = 'roadrunner:list';
    protected const DESCRIPTION = '(Deprecated) Displays a list of available job pipelines for the RoadRunner';

    public function perform(Console $console): int
    {
        $this->warning(
            'WARNING: This command has been deprecated and will be removed in the next major release of RoadRunner Bridge (version 3.0).',
        );

        return $console->run('rr:jobs:list', [], $this->output)->getCode();
    }
}
