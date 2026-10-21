<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\GRPC;

use Spiral\Files\Files;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Exception\CompileException;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistry;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistryInterface;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\FileRepository;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;
use Spiral\Tests\ConsoleTestCase;

final class GenerateCommandTest extends ConsoleTestCase
{
    private string $workDir;

    /** @var array<string, mixed> */
    private array $config = [];

    public function testGenerateServices(): void
    {
        $result = $this->generateGRPCService();

        $files = [
            'GRPC/Ping/PingServiceInterface.php',
            'GRPC/Ping/GPBMetadata/Service.php',
            'GRPC/Ping/PingRequest.php',
            'GRPC/Ping/PingResponse.php',
        ];

        $path = $this->getDirectoryByAlias('app') . 'proto/service.proto';
        $this->assertStringContainsString(
            \sprintf('Compiling `%s`:', \realpath($path) ?: $path),
            $result,
        );

        foreach ($files as $file) {
            $this->assertFileExists($this->getDirectoryByAlias('app') . $file);
            $this->assertStringContainsString(
                $file,
                $result,
            );
        }

        $this->assertMatchesRegularExpression(
            '#Proto file `.+proto[\\\\/]foo\\.proto` not found.#',
            $result,
        );
    }

    public function testGenerateWithoutServicesBasePath(): void
    {
        $this->configure('servicesBasePath', null);

        $result = $this->generate();

        $this->assertStringContainsString('GRPC/Ping/PingServiceInterface.php', $result);
        $this->assertFileExists($this->workDir . '/out/GRPC/Ping/PingServiceInterface.php');
    }

    public function testImportsResolveFromServicesBasePath(): void
    {
        $this->writeProto('common/meta.proto', <<<'PROTO'
            syntax = "proto3";
            package acme.common;
            option php_namespace = "Acme\\Common";
            option php_metadata_namespace = "Acme\\GPBMetadata\\Common";

            message Meta {
                string id = 1;
            }
            PROTO);
        $this->writeProto('talk/talk.proto', <<<'PROTO'
            syntax = "proto3";
            package acme.talk;
            option php_namespace = "Acme\\Talk";
            option php_metadata_namespace = "Acme\\GPBMetadata\\Talk";

            import "common/meta.proto";
            import "google/protobuf/timestamp.proto";

            service EchoService {
                rpc Echo (EchoMessage) returns (EchoMessage) {}
            }

            message EchoMessage {
                acme.common.Meta meta = 1;
                google.protobuf.Timestamp at = 2;
            }
            PROTO);
        $this->configure('servicesBasePath', $this->workDir . '/proto');
        $this->configure('services', [$this->workDir . '/proto/talk/talk.proto']);

        $this->generate('Acme');

        $this->assertFileExists($this->workDir . '/out/Talk/EchoServiceInterface.php');
        $this->assertFileExists($this->workDir . '/out/Talk/EchoMessage.php');
        $this->assertFileExists($this->workDir . '/out/GPBMetadata/Talk/Talk.php');
        // buf compiles imported files without generating them.
        $this->assertFileDoesNotExist($this->workDir . '/out/Common/Meta.php');
    }

    public function testProtoFileOutsideServicesBasePath(): void
    {
        $this->writeProto('outside.proto', 'syntax = "proto3";');
        $this->configure('services', [$this->workDir . '/proto/outside.proto']);

        $result = $this->generate();

        $this->assertStringContainsString('is outside of the services base path', $result);
        $this->assertDirectoryDoesNotExist($this->workDir . '/out');
    }

    public function testCompileError(): void
    {
        $this->writeProto('broken.proto', 'syntax = "proto3"; message {');
        $this->configure('servicesBasePath', $this->workDir . '/proto');
        $this->configure('services', [$this->workDir . '/proto/broken.proto']);

        $this->expectException(CompileException::class);
        $this->expectExceptionMessage('broken.proto');

        $this->generate();
    }

    public function testMissingBinary(): void
    {
        $this->configure('bufPath', $this->workDir . '/missing/buf');

        $result = $this->generate();

        $this->assertStringContainsString('`bufPath`', $result);
        $this->assertStringContainsString('vendor/bin/dload get buf', $result);
        $this->assertDirectoryDoesNotExist($this->workDir . '/out');
    }

    public function testGeneratorsReceiveGeneratedFiles(): void
    {
        $generator = new class implements GeneratorInterface {
            public array $calls = [];

            public function run(array $files, string $targetPath, string $namespace): void
            {
                $this->calls[] = [$files, $targetPath, $namespace];
            }
        };
        $this->configure('generators', [$generator]);

        $this->generate();

        $out = $this->workDir . '/out';
        $this->assertCount(1, $generator->calls);
        [$files, $targetPath, $namespace] = $generator->calls[0];
        \sort($files);
        $this->assertSame([
            $out . '/GRPC/Ping/GPBMetadata/Service.php',
            $out . '/GRPC/Ping/PingRequest.php',
            $out . '/GRPC/Ping/PingResponse.php',
            $out . '/GRPC/Ping/PingServiceInterface.php',
        ], $files);
        $this->assertSame($out, $targetPath);
        $this->assertSame('Spiral\\App', $namespace);
    }

    public function testUserTemplate(): void
    {
        $out = $this->workDir . '/out';
        $template = $this->workDir . '/buf.gen.yaml';
        (new Files())->write($template, \json_encode([
            'version' => 'v2',
            'plugins' => [
                ['protoc_builtin' => 'php', 'protoc_path' => $this->binary('protoc'), 'out' => $out . '/php'],
                ['local' => $this->binary('protoc-gen-php-grpc'), 'out' => $out . '/php'],
            ],
        ], \JSON_THROW_ON_ERROR), ensureDirectory: true);

        $this->configure('bufTemplate', $template);
        // The template does not need them; a wrong value proves they are not looked up.
        $this->configure('protocPath', $this->workDir . '/missing/protoc');
        $this->configure('pluginPath', $this->workDir . '/missing/protoc-gen-php-grpc');

        $generator = new class implements GeneratorInterface {
            public array $files = [];

            public function run(array $files, string $targetPath, string $namespace): void
            {
                $this->files = $files;
            }
        };
        $this->configure('generators', [$generator]);

        // Not regenerated, so not passed to the generators.
        (new Files())->write($out . '/stale.php', '<?php', ensureDirectory: true);
        \touch($out . '/stale.php', \time() - 60);

        $result = $this->generate();

        // The template output is used as is: the namespace prefix is not stripped.
        $interface = $out . '/php/Spiral/App/GRPC/Ping/PingServiceInterface.php';
        $this->assertFileExists($interface);
        $this->assertStringContainsString('php/Spiral/App/GRPC/Ping/PingServiceInterface.php', $result);

        \sort($generator->files);
        $this->assertSame([
            $out . '/php/Spiral/App/GRPC/Ping/GPBMetadata/Service.php',
            $out . '/php/Spiral/App/GRPC/Ping/PingRequest.php',
            $out . '/php/Spiral/App/GRPC/Ping/PingResponse.php',
            $interface,
        ], $generator->files);
    }

    public function testMissingUserTemplate(): void
    {
        $this->configure('bufTemplate', $this->workDir . '/missing.yaml');

        $result = $this->generate();

        $this->assertStringContainsString('`bufTemplate`', $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDir = (new Files())->normalizePath($this->getDirectoryByAlias('runtime') . 'grpc-generate');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->deleteGRPCService();

        $files = new Files();
        if ($files->isDirectory($this->workDir)) {
            $files->deleteDirectory($this->workDir);
        }
    }

    /**
     * The `grpc` config is delivered during boot and can no longer be patched, so the services that read it
     * are rebound instead.
     */
    private function configure(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
        $config = new GRPCConfig($this->config + $this->getConfig(GRPCConfig::CONFIG));

        $registry = new GeneratorRegistry();
        foreach ($config->getGenerators() as $generator) {
            \assert($generator instanceof GeneratorInterface);
            $registry->addGenerator($generator);
        }

        $container = $this->getContainer();
        $container->bindSingleton(GRPCConfig::class, $config);
        $container->bindSingleton(ProtoFilesRepositoryInterface::class, new FileRepository($config->getServices()));
        $container->bindSingleton(GeneratorRegistryInterface::class, $registry);
    }

    private function generate(string $namespace = 'Spiral\\App'): string
    {
        return $this->runCommand('grpc:generate', [
            'path' => $this->workDir . '/out',
            'namespace' => $namespace,
        ]);
    }

    private function writeProto(string $path, string $content): void
    {
        (new Files())->write($this->workDir . '/proto/' . $path, $content, ensureDirectory: true);
    }

    private function binary(string $name): string
    {
        return \dirname(__DIR__, 5) . \DIRECTORY_SEPARATOR . $name . (\PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    }
}
