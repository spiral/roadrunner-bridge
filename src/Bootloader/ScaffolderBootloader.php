<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\CentrifugoApiInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Console\Bootloader\ConsoleBootloader;
use Spiral\RoadRunnerBridge\Console\Command\Scaffolder\CentrifugoHandlerCommand;
use Spiral\RoadRunnerBridge\Console\Command\Scaffolder\TcpServiceCommand;
use Spiral\RoadRunnerBridge\Scaffolder\Declaration\CentrifugoHandlerDeclaration;
use Spiral\RoadRunnerBridge\Scaffolder\Declaration\TcpServiceDeclaration;
use Spiral\RoadRunnerBridge\Tcp\Server;
use Spiral\Scaffolder\Bootloader\ScaffolderBootloader as BaseScaffolderBootloader;

final class ScaffolderBootloader extends Bootloader
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function defineDependencies(): array
    {
        return [
            ConsoleBootloader::class,
            BaseScaffolderBootloader::class,
        ];
    }

    public function init(BaseScaffolderBootloader $scaffolder, ConsoleBootloader $console): void
    {
        $this->configureCommands($console);
        $this->configureDeclarations($scaffolder);
    }

    private function configureCommands(ConsoleBootloader $console): void
    {
        if ($this->container->has(CentrifugoApiInterface::class)) {
            $console->addCommand(CentrifugoHandlerCommand::class);
        }

        if ($this->container->has(Server::class)) {
            $console->addCommand(TcpServiceCommand::class);
        }
    }

    private function configureDeclarations(BaseScaffolderBootloader $scaffolder): void
    {
        $scaffolder->addDeclaration(CentrifugoHandlerDeclaration::TYPE, [
            'namespace' => 'Endpoint\\Centrifugo\\Handler',
            'postfix' => 'Handler',
            'class' => CentrifugoHandlerDeclaration::class,
        ]);

        $scaffolder->addDeclaration(TcpServiceDeclaration::TYPE, [
            'namespace' => 'Endpoint\\Tcp\\Service',
            'postfix' => 'Service',
            'class' => TcpServiceDeclaration::class,
        ]);
    }
}
