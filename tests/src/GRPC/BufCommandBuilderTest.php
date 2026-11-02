<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\BufCommandBuilder;
use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\BufTemplate;
use Testo\Assert;
use Testo\Test;

#[Test]
final class BufCommandBuilderTest
{
    public function buildWithoutPaths(): void
    {
        $builder = new BufCommandBuilder('/bin/buf');

        Assert::same($builder->build('/app/proto', '/tmp/buf.gen.yaml'), ['/bin/buf', 'generate', '/app/proto', '--template', '/tmp/buf.gen.yaml']);
    }

    public function buildWithPaths(): void
    {
        $builder = new BufCommandBuilder('C:\Program Files\buf.exe');

        Assert::same($builder->build('C:\app\proto', 'C:\tmp\buf.gen.yaml', ['C:\app\proto\echo', 'C:\app\proto\my service']), [
            'C:\Program Files\buf.exe', 'generate', 'C:\app\proto', '--template', 'C:\tmp\buf.gen.yaml',
            '--path', 'C:\app\proto\echo', '--path', 'C:\app\proto\my service',
        ]);
    }

    public function localTemplate(): void
    {
        $template = BufTemplate::local('C:\bin\protoc.exe', '/bin/protoc-gen-php-grpc');

        Assert::null($template->file);
        Assert::same(\json_decode($template->render('C:\tmp\out'), true, flags: \JSON_THROW_ON_ERROR), [
            'version' => 'v2',
            'plugins' => [
                ['protoc_builtin' => 'php', 'protoc_path' => 'C:\bin\protoc.exe', 'out' => 'C:\tmp\out'],
                ['local' => '/bin/protoc-gen-php-grpc', 'out' => 'C:\tmp\out'],
            ],
        ]);
    }

    public function userTemplate(): void
    {
        Assert::same(BufTemplate::user('/app/buf.gen.yaml')->file, '/app/buf.gen.yaml');
    }
}
