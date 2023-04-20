<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Scaffolder;

use Spiral\Files\FilesInterface;

final class CentrifugoHandlerCommandTest extends AbstractCommandTest
{
    public function testScaffold(): void
    {
        $this->className = $class = '\\Spiral\\App\\Scaffolder\\Endpoint\\Centrifugo\\Handler\\SampleHandler';

        $this->getConsole()->run('create:centrifugo-handler', [
            'name' => 'sample',
            '--comment' => 'Sample Handler'
        ]);

        clearstatcache();
        $this->assertTrue(\class_exists($class));

        $reflection = new \ReflectionClass($class);
        $content = $this->getContainer()->get(FilesInterface::class)->read($reflection->getFileName());

        $this->assertStringContainsString('strict_types=1', $content);
        $this->assertStringContainsString('Sample Handle', $reflection->getDocComment());
        $this->assertStringContainsString('final class SampleHandler', $content);
        $this->assertStringContainsString('namespace Spiral\App\Scaffolder\Endpoint\Centrifugo\Handler', $content);
        $this->assertTrue($reflection->hasMethod('__construct'));
        $this->assertTrue($reflection->hasMethod('handle'));
        $this->assertTrue($reflection->isFinal());
    }

    public function testScaffoldWithCustomNamespace(): void
    {
        $this->className = $class = '\\Spiral\\App\\Scaffolder\\Endpoint\\Centrifugo\\Other\\SampleHandler';

        $this->getConsole()->run('create:centrifugo-handler', [
            'name' => 'sample',
            '--namespace' => 'Spiral\\App\\Scaffolder\\Endpoint\\Centrifugo\\Other'
        ]);

        clearstatcache();
        $this->assertTrue(\class_exists($class));

        $reflection = new \ReflectionClass($class);
        $content = $this->getContainer()->get(FilesInterface::class)->read($reflection->getFileName());

        $this->assertStringContainsString(
            'Endpoint/Centrifugo/Other/SampleHandler.php',
            \str_replace('\\', '/', $reflection->getFileName())
        );
        $this->assertStringContainsString('namespace Spiral\App\Scaffolder\Endpoint\Centrifugo\Other', $content);
    }
}
