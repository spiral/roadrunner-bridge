<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Scaffolder;

use Spiral\Files\FilesInterface;

final class TcpServiceCommandTest extends AbstractCommandTest
{
    public function testScaffold(): void
    {
        $this->className = $class = '\\Spiral\\App\\Scaffolder\\Endpoint\\Tcp\\Service\\SampleService';

        $this->getConsole()->run('create:tcp-service', [
            'name' => 'sample',
            '--comment' => 'Sample Service',
        ]);

        clearstatcache();
        $this->assertTrue(\class_exists($class));

        $reflection = new \ReflectionClass($class);
        $content = $this->getContainer()->get(FilesInterface::class)->read($reflection->getFileName());

        $this->assertStringContainsString('strict_types=1', $content);
        $this->assertStringContainsString('Sample Service', $reflection->getDocComment());
        $this->assertStringContainsString('final class SampleService implements ServiceInterface', $content);
        $this->assertStringContainsString('Spiral\App\Scaffolder\Endpoint\Tcp\Service', $content);
        $this->assertTrue($reflection->hasMethod('handle'));
        $this->assertStringContainsString('public function handle(Request $request): ResponseInterface', $content);
        $this->assertStringContainsString('return new RespondMessage(\'some message\', true);', $content);
        $this->assertTrue($reflection->isFinal());
    }

    public function testScaffoldWithCustomNamespace(): void
    {
        $this->className = $class = '\\Spiral\\App\\Scaffolder\\Endpoint\\Tcp\\Other\\SampleService';

        $this->getConsole()->run('create:tcp-service', [
            'name' => 'sample',
            '--namespace' => 'Spiral\\App\\Scaffolder\\Endpoint\\Tcp\\Other',
        ]);

        clearstatcache();
        $this->assertTrue(\class_exists($class));

        $reflection = new \ReflectionClass($class);
        $content = $this->getContainer()->get(FilesInterface::class)->read($reflection->getFileName());

        $this->assertStringContainsString(
            'Endpoint/Tcp/Other/SampleService.php',
            \str_replace('\\', '/', $reflection->getFileName())
        );
        $this->assertStringContainsString('namespace Spiral\App\Scaffolder\Endpoint\Tcp\Other', $content);
    }
}
