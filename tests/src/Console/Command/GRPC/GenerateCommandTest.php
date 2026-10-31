<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\GRPC;

use Spiral\Files\Files;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Exception\CompileException;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistryInterface;
use Spiral\RoadRunnerBridge\GRPC\Internal\FileRepository;
use Spiral\RoadRunnerBridge\GRPC\Internal\GeneratorRegistry;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;
use Spiral\Tests\ConsoleTestCase;
use Testo\Assert;
use Testo\Expect;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

final class GenerateCommandTest extends ConsoleTestCase
{
    private string $workDir;

    /** @var array<string, mixed> */
    private array $config = [];

    #[Test]
    public function generateServices(): void
    {
        $result = $this->generateGRPCService();

        $files = [
            'GRPC/Ping/PingServiceInterface.php',
            'GRPC/Ping/GPBMetadata/Service.php',
            'GRPC/Ping/PingRequest.php',
            'GRPC/Ping/PingResponse.php',
        ];

        $path = $this->getDirectoryByAlias('app') . 'proto/service.proto';
        Assert::string($result)->contains(\sprintf('Compiling `%s`:', \realpath($path) ?: $path));

        foreach ($files as $file) {
            Assert::true(\file_exists($this->getDirectoryByAlias('app') . $file));
            Assert::string($result)->contains($file);
        }

        Assert::string($result)->matchesRegex('#Proto file `.+proto[\\\\/]foo\\.proto` not found.#');
    }

    #[Test]
    public function generateWithoutServicesBasePath(): void
    {
        $this->configure('servicesBasePath', null);

        $result = $this->generate();

        Assert::string($result)->contains('GRPC/Ping/PingServiceInterface.php');
        Assert::true(\file_exists($this->workDir . '/out/GRPC/Ping/PingServiceInterface.php'));
    }

    #[Test]
    public function importsResolveFromServicesBasePath(): void
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

        Assert::true(\file_exists($this->workDir . '/out/Talk/EchoServiceInterface.php'));
        Assert::true(\file_exists($this->workDir . '/out/Talk/EchoMessage.php'));
        Assert::true(\file_exists($this->workDir . '/out/GPBMetadata/Talk/Talk.php'));
        // buf compiles imported files without generating them.
        Assert::false(\file_exists($this->workDir . '/out/Common/Meta.php'));
    }

    #[Test]
    public function protoFileOutsideServicesBasePath(): void
    {
        $this->writeProto('outside.proto', 'syntax = "proto3";');
        $this->configure('services', [$this->workDir . '/proto/outside.proto']);

        $result = $this->generate();

        Assert::string($result)->contains('is outside of the services base path');
        Assert::false(\is_dir($this->workDir . '/out'));
    }

    #[Test]
    public function compileError(): void
    {
        $this->writeProto('broken.proto', 'syntax = "proto3"; message {');
        $this->configure('servicesBasePath', $this->workDir . '/proto');
        $this->configure('services', [$this->workDir . '/proto/broken.proto']);

        Expect::exception(CompileException::class)->withMessageContaining('broken.proto');

        $this->generate();
    }

    #[Test]
    public function missingBinary(): void
    {
        $this->configure('bufPath', $this->workDir . '/missing/buf');

        $result = $this->generate();

        Assert::string($result)->contains('`bufPath`')->contains('vendor/bin/dload get buf');
        Assert::false(\is_dir($this->workDir . '/out'));
    }

    #[Test]
    public function generatorsReceiveGeneratedFiles(): void
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
        Assert::count($generator->calls, 1);
        [$files, $targetPath, $namespace] = $generator->calls[0];
        \sort($files);
        Assert::same($files, [
            $out . '/GRPC/Ping/GPBMetadata/Service.php',
            $out . '/GRPC/Ping/PingRequest.php',
            $out . '/GRPC/Ping/PingResponse.php',
            $out . '/GRPC/Ping/PingServiceInterface.php',
        ]);
        Assert::same($targetPath, $out);
        Assert::same($namespace, 'Spiral\\App');
    }

    #[Test]
    public function userTemplate(): void
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
        Assert::true(\file_exists($interface));
        Assert::string($result)->contains('php/Spiral/App/GRPC/Ping/PingServiceInterface.php');

        \sort($generator->files);
        Assert::same($generator->files, [
            $out . '/php/Spiral/App/GRPC/Ping/GPBMetadata/Service.php',
            $out . '/php/Spiral/App/GRPC/Ping/PingRequest.php',
            $out . '/php/Spiral/App/GRPC/Ping/PingResponse.php',
            $interface,
        ]);
    }

    #[Test]
    public function missingUserTemplate(): void
    {
        $this->configure('bufTemplate', $this->workDir . '/missing.yaml');

        $result = $this->generate();

        Assert::string($result)->contains('`bufTemplate`');
    }

    #[BeforeTest]
    protected function prepare(): void
    {
        $this->workDir = (new Files())->normalizePath($this->getDirectoryByAlias('runtime') . 'grpc-generate');
    }

    #[AfterTest]
    protected function cleanUp(): void
    {
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
