<?php

declare(strict_types=1);

namespace Spiral\Tests\Bootloader;

use Spiral\RoadRunnerBridge\Scaffolder\Declaration\CentrifugoHandlerDeclaration;
use Spiral\Scaffolder\Config\ScaffolderConfig;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class ScaffolderBootloaderTest extends TestCase
{
    #[Test]
    public function commandsShouldBeRegistered(): void
    {
        $this->assertCommandRegistered('create:centrifugo-handler');
    }

    #[Test]
    public function declarationsShouldBeRegistered(): void
    {
        $config = $this->getConfig(ScaffolderConfig::CONFIG);

        Assert::same($config['defaults']['declarations'][CentrifugoHandlerDeclaration::TYPE], [
            'namespace' => 'Endpoint\\Centrifugo\\Handler',
            'postfix' => 'Handler',
            'class' => CentrifugoHandlerDeclaration::class,
        ]);
    }
}
