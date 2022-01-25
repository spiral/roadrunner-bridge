<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\GRPC;

use Spiral\Console\Command;
use Spiral\RoadRunnerBridge\GRPC\LocatorInterface;

final class ListCommand extends Command
{
    protected const NAME = 'grpc:services';
    protected const DESCRIPTION = 'List available GRPC services';

    public function perform(LocatorInterface $locator): void
    {
        $services = $locator->getServices();

        if ($services === []) {
            $this->writeln('<comment>No GRPC services were found.</comment>');

            return;
        }

        $table = $this->table([
            'Service:',
            'Implementation:',
            'File:',
        ]);

        foreach ($services as $interface => $instance) {
            $table->addRow([
                $interface::NAME,
                get_class($instance),
                (new \ReflectionObject($instance))->getFileName(),
            ]);
        }

        $table->render();
    }
}
