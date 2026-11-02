<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\Files\Files;
use Spiral\RoadRunnerBridge\Config\GRPCConfig;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorInterface;
use Spiral\RoadRunnerBridge\GRPC\Generator\GeneratorRegistryInterface;
use Spiral\RoadRunnerBridge\GRPC\Internal\Compiler\GeneratorRegistry;
use Spiral\RoadRunnerBridge\GRPC\Internal\ProtoRepository\FileRepository;
use Spiral\RoadRunnerBridge\GRPC\ProtoRepository\ProtoFilesRepositoryInterface;
use Spiral\Tests\ConsoleTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;

final class GenerateCommandTest extends ConsoleTestCase
{
    private string $workDir;

    /** @var array<string, mixed> */
    private array $config = [];

    /** Exit code of the last {@see generate()} run. */
    private int $exitCode = -1;

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
        $this->writeProto('base/id.proto', <<<'PROTO'
            syntax = "proto3";
            package acme.base;
            option php_namespace = "Acme\\Base";
            option php_metadata_namespace = "Acme\\GPBMetadata\\Base";

            message Id {
                string value = 1;
            }
            PROTO);
        $this->writeProto('common/meta.proto', <<<'PROTO'
            syntax = "proto3";
            package acme.common;
            option php_namespace = "Acme\\Common";
            option php_metadata_namespace = "Acme\\GPBMetadata\\Common";

            import "base/id.proto";

            message Meta {
                acme.base.Id id = 1;
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

        Assert::same($this->exitCode, Command::SUCCESS);
        Assert::true(\file_exists($this->workDir . '/out/Talk/EchoServiceInterface.php'));
        Assert::true(\file_exists($this->workDir . '/out/Talk/EchoMessage.php'));
        Assert::true(\file_exists($this->workDir . '/out/GPBMetadata/Talk/Talk.php'));
        // The generated messages reference the imported ones, transitively, so those are generated too.
        Assert::true(\file_exists($this->workDir . '/out/Common/Meta.php'));
        Assert::true(\file_exists($this->workDir . '/out/GPBMetadata/Common/Meta.php'));
        Assert::true(\file_exists($this->workDir . '/out/Base/Id.php'));
        // Well-Known Types come with google/protobuf.
        Assert::false(\file_exists($this->workDir . '/out/Google/Protobuf/Timestamp.php'));
    }

    #[Test]
    public function importStatementsAreReadAsProtobufDoes(): void
    {
        foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $name) {
            $this->writeProto(\sprintf('common/%s.proto', \strtolower($name)), <<<PROTO
                syntax = "proto3";
                package acme.common;
                option php_namespace = "Acme\\\\Common";

                message {$name} {}
                PROTO);
        }
        $this->writeProto('legacy/broken.proto', 'syntax = "proto3"; message Broken { Missing m = 1; }');
        $this->writeProto('google/api/http.proto', <<<'PROTO'
            syntax = "proto3";
            package google.api;

            message HttpRule {}
            PROTO);
        $this->writeProto('talk/talk.proto', \str_replace('%LONG%', \str_repeat('x', 20000), <<<'PROTO'
            // Routes: /v1/notes/*
            syntax = "proto3";
            package acme.talk;
            option php_namespace = "Acme\\Talk";
            option go_package = "https://example.com/notes/*"; import "common/d.proto";

            import "common/a.proto"; import "common/b.proto";
            import 'common/c.proto';
            import/* glued */"common/e.proto";
            option java_package = "%LONG%"; import public"common/f.proto";
            import weak 'common/g.proto';
            import "google/api/http.proto";
            /*
            import "legacy/broken.proto";
            */
            // import "legacy/broken.proto";

            message Note {
                acme.common.A a = 1;
                acme.common.B b = 2;
                acme.common.C c = 3;
                acme.common.D d = 4;
                acme.common.E e = 5;
                acme.common.F f = 6;
                acme.common.G g = 7;
                google.api.HttpRule rule = 8;
            }
            PROTO));
        $this->configure('servicesBasePath', $this->workDir . '/proto');
        $this->configure('services', [$this->workDir . '/proto/talk/talk.proto']);

        $this->generate('Acme');

        // A commented-out import is not followed: the file it names does not compile.
        Assert::same($this->exitCode, Command::SUCCESS);
        Assert::true(\file_exists($this->workDir . '/out/Common/A.php'));
        Assert::true(\file_exists($this->workDir . '/out/Common/B.php'));
        Assert::true(\file_exists($this->workDir . '/out/Common/C.php'));
        // `//` and `/*` inside a string or a line comment do not hide the imports after them.
        Assert::true(\file_exists($this->workDir . '/out/Common/D.php'));
        Assert::true(\file_exists($this->workDir . '/out/Common/E.php'));
        // After a long string literal, and with no space before the path.
        Assert::true(\file_exists($this->workDir . '/out/Common/F.php'));
        Assert::true(\file_exists($this->workDir . '/out/Common/G.php'));
        // A vendored googleapis copy would duplicate the classes of google/common-protos.
        Assert::false(\file_exists($this->workDir . '/out/Google/Api/HttpRule.php'));
    }

    #[Test]
    public function importsAreNotAddedToUserTemplate(): void
    {
        $this->writeProto('common/meta.proto', <<<'PROTO'
            syntax = "proto3";
            package acme.common;
            option php_namespace = "Acme\\Common";

            message Meta {}
            PROTO);
        $this->writeProto('talk/talk.proto', <<<'PROTO'
            syntax = "proto3";
            package acme.talk;
            option php_namespace = "Acme\\Talk";

            import "common/meta.proto";

            message Note {
                acme.common.Meta meta = 1;
            }
            PROTO);
        $out = $this->workDir . '/out';
        $this->configure('bufTemplate', $this->writeTemplate([
            ['protoc_builtin' => 'php', 'protoc_path' => $this->binary('protoc'), 'out' => $out],
        ]));
        $this->configure('servicesBasePath', $this->workDir . '/proto');
        $this->configure('services', [$this->workDir . '/proto/talk/talk.proto']);

        $this->generate('Acme');

        Assert::true(\file_exists($out . '/Acme/Talk/Note.php'));
        // The template decides on imports itself (`include_imports`).
        Assert::false(\file_exists($out . '/Acme/Common/Meta.php'));
    }

    #[Test]
    public function generatorsAreSkippedAfterAFailure(): void
    {
        $generator = new class implements GeneratorInterface {
            public bool $called = false;

            public function run(array $files, string $targetPath, string $namespace): void
            {
                $this->called = true;
            }
        };
        $this->configure('generators', [$generator]);

        // The default config lists a missing `foo.proto`.
        $result = $this->generate();

        Assert::same($this->exitCode, Command::FAILURE);
        Assert::false($generator->called);
        Assert::string($result)->contains('Generators were skipped');
    }

    #[Test]
    public function namespacePrefixIsCutByWholeSegments(): void
    {
        $this->writeProto('extra.proto', <<<'PROTO'
            syntax = "proto3";
            package extra;
            option php_namespace = "AcmeExtra\\Talk";
            option php_metadata_namespace = "AcmeExtra\\GPBMetadata";

            message Note {
                string text = 1;
            }
            PROTO);
        $this->configure('servicesBasePath', $this->workDir . '/proto');
        $this->configure('services', [$this->workDir . '/proto/extra.proto']);

        $this->generate('Acme');

        Assert::true(\file_exists($this->workDir . '/out/AcmeExtra/Talk/Note.php'));
        Assert::false(\file_exists($this->workDir . '/out/Extra/Talk/Note.php'));
    }

    #[Test]
    public function missingProtoFileFails(): void
    {
        $result = $this->generate();

        Assert::same($this->exitCode, Command::FAILURE);
        Assert::string($result)->matchesRegex("#Proto file `.+foo\\.proto` not found.\\R#");
        // The other files are still generated.
        Assert::true(\file_exists($this->workDir . '/out/GRPC/Ping/PingServiceInterface.php'));
    }

    #[Test]
    public function emptyServicesEntryIsReported(): void
    {
        $this->configure('services', [$this->serviceProto(), '']);

        $result = $this->generate();

        Assert::same($this->exitCode, Command::FAILURE);
        Assert::string($result)->contains('Proto file `` not found.');
        Assert::true(\file_exists($this->workDir . '/out/GRPC/Ping/PingServiceInterface.php'));
    }

    #[Test]
    public function outputHasNoRawAnsiCodes(): void
    {
        $result = $this->generate();

        Assert::string($result)->contains('GRPC/Ping/PingServiceInterface.php');
        Assert::false(\str_contains($result, "\e["));
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

        $result = $this->generate();

        Assert::same($this->exitCode, Command::FAILURE);
        Assert::string($result)->contains('Error:')->contains('broken.proto');
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
        $this->configure('services', [$this->serviceProto()]);

        $this->generate();

        $out = $this->workDir . '/out';
        Assert::same($this->exitCode, Command::SUCCESS);
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
        $template = $this->writeTemplate([
            ['protoc_builtin' => 'php', 'protoc_path' => $this->binary('protoc'), 'out' => $out . '/php'],
            ['local' => $this->binary('protoc-gen-php-grpc'), 'out' => $out . '/php'],
        ]);

        $this->configure('bufTemplate', $template);
        $this->configure('services', [$this->serviceProto()]);
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
    public function relativePathsResolveFromRoot(): void
    {
        $files = new Files();
        $root = $this->getDirectoryByAlias('root');
        $template = $this->writeTemplate([
            ['protoc_builtin' => 'php', 'protoc_path' => $this->binary('protoc'), 'out' => $files->relativePath($this->workDir . '/rel', $root)],
        ]);
        $this->configure('bufTemplate', $files->relativePath($template, $root));
        $this->configure('servicesBasePath', $files->relativePath($this->getDirectoryByAlias('app') . 'proto', $root));
        $this->configure('services', [$this->serviceProto()]);

        $cwd = (string) \getcwd();
        \chdir(\sys_get_temp_dir());
        try {
            $this->generate();
        } finally {
            \chdir($cwd);
        }

        Assert::same($this->exitCode, Command::SUCCESS);
        Assert::true(\file_exists($this->workDir . '/rel/Spiral/App/GRPC/Ping/PingRequest.php'));
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
        $output = new BufferedOutput();
        $this->exitCode = $this->getConsole()->run('grpc:generate', [
            'path' => $this->workDir . '/out',
            'namespace' => $namespace,
        ], $output)->getCode();

        return $output->fetch();
    }

    private function writeProto(string $path, string $content): void
    {
        (new Files())->write($this->workDir . '/proto/' . $path, $content, ensureDirectory: true);
    }

    /**
     * @param list<array<string, string>> $plugins
     *
     * @return string Path to the written buf.gen.yaml.
     */
    private function writeTemplate(array $plugins): string
    {
        $template = $this->workDir . '/buf.gen.yaml';
        $content = \json_encode(['version' => 'v2', 'plugins' => $plugins], \JSON_THROW_ON_ERROR);
        (new Files())->write($template, $content, ensureDirectory: true);

        return $template;
    }

    /**
     * The test application's proto file that exists, unlike `foo.proto` from the default config.
     */
    private function serviceProto(): string
    {
        return $this->getDirectoryByAlias('app') . 'proto/service.proto';
    }

    private function binary(string $name): string
    {
        return \dirname(__DIR__, 3) . \DIRECTORY_SEPARATOR . $name . (\PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
    }
}
