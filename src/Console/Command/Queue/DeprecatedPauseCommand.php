<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Queue;

use Spiral\Console\Command;
use Spiral\Console\Console;

/**
 * @deprecated Will be removed in the next major release of RoadRunner Bridge (version 3.0)
 */
final class DeprecatedPauseCommand extends Command
{
    protected const SIGNATURE = 'roadrunner:pause {pipeline : Pipeline name}';
    protected const DESCRIPTION = '(Deprecated) Pauses the consumption of jobs for the specified pipeline in the RoadRunner.';

    public function perform(Console $console): int
    {
        $this->warning(
            'WARNING: This command has been deprecated and will be removed in the next major release of RoadRunner Bridge (version 3.0).',
        );

        return $console->run('rr:jobs:pause', [
            'pipeline' => $this->argument('pipeline'),
        ], $this->output)->getCode();
    }
}
