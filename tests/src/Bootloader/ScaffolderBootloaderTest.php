<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\RoadRunnerBridge\Scaffolder\Declaration\CentrifugoHandlerDeclaration;
use Spiral\Scaffolder\Config\ScaffolderConfig;
use Spiral\Tests\TestCase;

final class ScaffolderBootloaderTest extends TestCase
{
    public function testCommandsShouldBeRegistered(): void
    {
        $this->assertCommandRegistered('create:centrifugo-handler');
    }

    public function testDeclarationsShouldBeRegistered(): void
    {
        $config = $this->getConfig(ScaffolderConfig::CONFIG);

        $this->assertSame([
            'namespace' => 'Endpoint\\Centrifugo\\Handler',
            'postfix' => 'Handler',
            'class' => CentrifugoHandlerDeclaration::class,
        ], $config['defaults']['declarations'][CentrifugoHandlerDeclaration::TYPE]);
    }
}
