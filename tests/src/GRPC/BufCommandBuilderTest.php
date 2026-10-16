<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use PHPUnit\Framework\TestCase;
use Spiral\RoadRunnerBridge\GRPC\Internal\BufCommandBuilder;
use Spiral\RoadRunnerBridge\GRPC\Internal\BufTemplate;

final class BufCommandBuilderTest extends TestCase
{
    public function testBuildWithoutPaths(): void
    {
        $builder = new BufCommandBuilder('/bin/buf');

        $this->assertSame(
            ['/bin/buf', 'generate', '/app/proto', '--template', '/tmp/buf.gen.yaml'],
            $builder->build('/app/proto', '/tmp/buf.gen.yaml'),
        );
    }

    public function testBuildWithPaths(): void
    {
        $builder = new BufCommandBuilder('C:\Program Files\buf.exe');

        $this->assertSame(
            [
                'C:\Program Files\buf.exe', 'generate', 'C:\app\proto', '--template', 'C:\tmp\buf.gen.yaml',
                '--path', 'C:\app\proto\echo', '--path', 'C:\app\proto\my service',
            ],
            $builder->build('C:\app\proto', 'C:\tmp\buf.gen.yaml', ['C:\app\proto\echo', 'C:\app\proto\my service']),
        );
    }

    public function testLocalTemplate(): void
    {
        $template = BufTemplate::local('C:\bin\protoc.exe', '/bin/protoc-gen-php-grpc');

        $this->assertNull($template->file);
        $this->assertSame(
            [
                'version' => 'v2',
                'plugins' => [
                    ['protoc_builtin' => 'php', 'protoc_path' => 'C:\bin\protoc.exe', 'out' => 'C:\tmp\out'],
                    ['local' => '/bin/protoc-gen-php-grpc', 'out' => 'C:\tmp\out'],
                ],
            ],
            \json_decode($template->render('C:\tmp\out'), true, flags: \JSON_THROW_ON_ERROR),
        );
    }

    public function testUserTemplate(): void
    {
        $this->assertSame('/app/buf.gen.yaml', BufTemplate::user('/app/buf.gen.yaml')->file);
    }
}
