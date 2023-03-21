<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Cache;

use Spiral\Cache\CacheStorageProviderInterface;
use Spiral\Console\Command;

/**
 * @deprecated Will be removed in the next major release of RoadRunner Bridge (version 3.0)
 */
final class ClearCommand extends Command
{
    protected const SIGNATURE = 'cache:clear {storage? : Storage name}';
    protected const DESCRIPTION = 'Clears the cache for the specified storage in the Spiral cache component.';

    public function perform(CacheStorageProviderInterface $provider): int
    {
        $this->warning('WARNING: This command has been deprecated and will be removed in the next major release of RoadRunner Bridge (version 3.0).');

        if ($this->isVerbose()) {
            $this->writeln('<info>Cleaning application cache:</info>');
        }

        $provider->storage($this->argument('storage'))->clear();

        $this->writeln('<info>Cache has been cleared.</info>');

        return self::SUCCESS;
    }
}
