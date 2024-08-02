<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\Files\FilesInterface;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\ProtocCommandBuilder;
use Spiral\Tests\TestCase;
use Mockery as m;

final class ProtocCommandBuilderTest extends TestCase
{
    public function testBuild(): void
    {
        $builder = new ProtocCommandBuilder(
            $files = m::mock(FilesInterface::class),
            new GRPCConfig([
                'servicesBasePath' => 'path4',
            ]),
            'path3'
        );

        $files->shouldReceive('ensureDirectory')
            ->with($directory = \sys_get_temp_dir() . '/' . \spl_object_hash($builder))
            ->andReturn();

        $files->shouldReceive('normalizePath')->with($directory, true)->andReturn('path5');

        $files->shouldReceive('getFiles')->with('path1')
            ->andReturn([
                'message.proto.tmp',
                'service.proto.tmp',
                'message.proto',
                'service.proto',
                '.gitignore',
                '.gitattributes',
            ]);

        $this->assertSame(
            "protoc --plugin=path3 --php_out='path2' --php-grpc_out='path2' -I='path1' -I='path4' 'message.proto' 'service.proto' 2>&1",
            $builder->build('path1', 'path2')
        );
    }

    public function testBuildWithNullServicesBasePath(): void
    {
        $builder = new ProtocCommandBuilder(
            $files = m::mock(FilesInterface::class),
            new GRPCConfig([
                'servicesBasePath' => null,
            ]),
            'path3'
        );

        $files->shouldReceive('getFiles')->with('path1')
            ->andReturn([
                'message.proto.tmp',
                'service.proto.tmp',
                'message.proto',
                'service.proto',
                '.gitignore',
                '.gitattributes',
            ]);

        $this->assertSame(
            "protoc --plugin=path3 --php_out='path2' --php-grpc_out='path2' -I='path1' 'message.proto' 'service.proto' 2>&1",
            $builder->build('path1', 'path2')
        );
    }
}
