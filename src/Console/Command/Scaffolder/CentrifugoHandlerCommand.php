<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Scaffolder;

use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Attribute\Question;
use Spiral\RoadRunnerBridge\Scaffolder\Declaration\CentrifugoHandlerDeclaration;
use Spiral\Scaffolder\Command\AbstractCommand;

#[AsCommand(name: 'create:centrifugo-handler', description: 'Create Centrifugo handler declaration')]
final class CentrifugoHandlerCommand extends AbstractCommand
{
    #[Argument(description: 'Centrifugo handler name')]
    #[Question(question: 'What would you like to name the Centrifugo handler?')]
    private string $name;

    #[Option(shortcut: 'c', description: 'Optional comment to add as class header')]
    private ?string $comment = null;

    #[Option(description: 'Optional, specify a custom namespace')]
    private ?string $namespace = null;

    public function perform(): int
    {
        $declaration = $this->createDeclaration(CentrifugoHandlerDeclaration::class);

        $this->writeDeclaration($declaration);

        return self::SUCCESS;
    }
}
