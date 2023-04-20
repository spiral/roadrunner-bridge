<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Scaffolder;

use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Attribute\Question;
use Spiral\RoadRunnerBridge\Scaffolder\Declaration\TcpServiceDeclaration;
use Spiral\Scaffolder\Command\AbstractCommand;

#[AsCommand(name: 'create:tcp-service', description: 'Create TCP service declaration')]
final class TcpServiceCommand extends AbstractCommand
{
    #[Argument(description: 'TCP service name')]
    #[Question(question: 'What would you like to name the Tcp service?')]
    private string $name;

    #[Option(shortcut: 'c', description: 'Optional comment to add as class header')]
    private ?string $comment = null;

    #[Option(description: 'Optional, specify a custom namespace')]
    private ?string $namespace = null;

    public function perform(): int
    {
        $declaration = $this->createDeclaration(TcpServiceDeclaration::class);

        $this->writeDeclaration($declaration);

        return self::SUCCESS;
    }
}
