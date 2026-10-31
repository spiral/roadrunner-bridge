<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal;

use Spiral\Console\Command;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;

/**
 * @internal
 */
final class ListCommand extends Command
{
    protected const SIGNATURE = 'grpc:services';
    protected const DESCRIPTION = 'List available GRPC services';

    public function perform(LocatorInterface $locator): int
    {
        $services = $locator->getServices();

        if ($services === []) {
            $this->writeln('<comment>No GRPC services were found.</comment>');

            return self::SUCCESS;
        }

        $table = $this->table([
            'Service:',
            'Implementation:',
            'File:',
        ]);

        foreach ($services as $interface => $reflection) {
            $table->addRow([
                $interface::NAME,
                $reflection->getName(),
                $reflection->getFileName(),
            ]);
        }

        $table->render();

        return self::SUCCESS;
    }
}
