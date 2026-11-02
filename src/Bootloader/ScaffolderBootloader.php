<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Bootloader;

use Psr\Container\ContainerInterface;
use RoadRunner\Centrifugo\CentrifugoApiInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Console\Bootloader\ConsoleBootloader;
use Spiral\RoadRunnerBridge\Scaffolder\Declaration\CentrifugoHandlerDeclaration;
use Spiral\RoadRunnerBridge\Scaffolder\Internal\CentrifugoHandlerCommand;
use Spiral\Scaffolder\Bootloader\ScaffolderBootloader as BaseScaffolderBootloader;

/**
 * @api
 */
final class ScaffolderBootloader extends Bootloader
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    #[\Override]
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
    }

    private function configureDeclarations(BaseScaffolderBootloader $scaffolder): void
    {
        $scaffolder->addDeclaration(CentrifugoHandlerDeclaration::TYPE, [
            'namespace' => 'Endpoint\\Centrifugo\\Handler',
            'postfix' => 'Handler',
            'class' => CentrifugoHandlerDeclaration::class,
        ]);
    }
}
