<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Cache;

use Spiral\Cache\CacheStorageProviderInterface;
use Spiral\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

final class ClearCommand extends Command
{
    protected const NAME = 'cache:clear';
    protected const DESCRIPTION = 'Clear cache';
    protected const ARGUMENTS = [
        ['storage', InputArgument::OPTIONAL, 'Storage name'],
    ];

    public function perform(CacheStorageProviderInterface $provider): int
    {
        if ($this->isVerbose()) {
            $this->writeln('<info>Cleaning application cache:</info>');
        }

        $provider->storage($this->argument('storage'))->clear();

        $this->writeln('<info>Cache has been cleared.</info>');

        return self::SUCCESS;
    }
}
