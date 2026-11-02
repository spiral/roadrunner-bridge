<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Internal\Path;
use Spiral\Tests\Acceptance\Support\GenerateProject;
use Spiral\Tests\Acceptance\Support\ProcessResult;
use Testo\Assert;
use Testo\Lifecycle\AfterTest;
use Testo\Test;

/**
 * `grpc:generate` run in a separate PHP process against the real buf, protoc and protoc-gen-php-grpc.
 */
#[Test]
final class GrpcGenerateTest
{
    /** What the default config generates: `shop/order.proto` with its imports and `notes/note.proto`. */
    private const GENERATED = [
        'generated/AcceptanceExtra/GPBMetadata/Notes/Note.php',
        'generated/AcceptanceExtra/Notes/Note.php',
        'generated/AcceptanceExtra/Notes/NoteServiceInterface.php',
        'generated/Common/Money.php',
        'generated/GPBMetadata/Common/Money.php',
        'generated/GPBMetadata/Shop/Order.php',
        'generated/GPBMetadata/Types/Currency/Currency.php',
        'generated/GPBMetadata/Types/Customer/Customer.php',
        'generated/Shop/Order.php',
        'generated/Shop/OrderServiceInterface.php',
        'generated/Shop/PlaceOrderRequest.php',
        'generated/Types/Currency.php',
        'generated/Types/Customer.php',
    ];

    private const AUTOLOAD_SUGGESTION = '  "AcceptanceExtra\\\\": "generated/AcceptanceExtra/"';

    /** @var list<GenerateProject> */
    private array $projects = [];

    #[AfterTest]
    public function removeProjects(): void
    {
        # The case instance is shared by its tests.
        $projects = $this->projects;
        $this->projects = [];
        \array_map(static fn(GenerateProject $project) => $project->remove(), $projects);
    }

    public function generatesServicesWithTheirImports(): void
    {
        $project = $this->project();

        $result = $project->generate(['--no-ansi']);

        Assert::same($result->exitCode, 0, $result->output);
        # No Google classes: neither the Well-Known Type nor the vendored googleapis copy. No `Legacy`: its import
        # is commented out. `AcceptanceExtra` keeps its name: the prefix `Acceptance` is cut on whole segments.
        Assert::same($project->files('generated'), self::GENERATED);
        Assert::string($result->output)->ignoringLineEndings()
            ->contains('• generated/Shop/OrderServiceInterface.php')
            ->contains('• generated/Types/Currency.php')
            ->contains("Classes outside the base namespace `Acceptance`")
            ->contains(self::AUTOLOAD_SUGGESTION . "\n")
            ->notContains('Generators were skipped');
    }

    public function resultDoesNotDependOnWorkingDirectory(): void
    {
        $fromRepository = $this->project();
        $fromProtoDir = $this->project();

        $expected = $fromRepository->generate(['--no-ansi']);
        $actual = $fromProtoDir->generate(['--no-ansi'], cwd: $fromProtoDir->root . '/proto/shop');

        Assert::same($actual->exitCode, 0, $actual->output);
        Assert::same($fromProtoDir->files('generated'), $fromRepository->files('generated'));
        Assert::same(self::rootless($actual, $fromProtoDir), self::rootless($expected, $fromRepository));
    }

    public function generatedCodeIsUsable(): void
    {
        $project = $this->project();
        $generated = $project->generate(['--no-ansi']);
        Assert::same($generated->exitCode, 0, $generated->output);
        Assert::same(\preg_match('#^  "(\w+)\\\\\\\\": "([^"]+)"$#m', $generated->output, $suggestion), 1);

        $result = GenerateProject::php([GenerateProject::APP_DIR . '/roundtrip.php'], env: [
            'GENERATED_AUTOLOAD' => \json_encode([
                'Acceptance\\' => $project->root . '/generated/',
                $suggestion[1] . '\\' => $project->root . '/' . $suggestion[2],
            ]),
        ]);

        Assert::same($result->exitCode, 0, $result->output);
        Assert::same(\json_decode($result->output, true), [
            'reencoded' => true,
            'id' => 'order-1',
            'units' => 42,
            'currency' => 'EUR',
            'json' => '{"id":"order-1","total":{"units":"42","currency":"EUR"},"placedAt":"2023-11-14T22:13:20Z"}',
            'note' => 'hello',
        ], $result->output);
    }

    public function reportsBrokenProtoFile(): void
    {
        $project = $this->project([
            'services' => ['proto/shop/order.proto', 'proto/legacy/broken.proto', 'proto/notes/note.proto'],
        ]);

        $result = $project->generate(['--no-ansi']);

        Assert::same($result->exitCode, 1, $result->output);
        Assert::string($result->output)->ignoringLineEndings()
            ->matchesRegex('/^Error: .*broken\.proto:\d+:\d+:.*`?Missing`?/m')
            ->contains("Generators were skipped because of the errors above.\n")
            ->notContains('Stack trace')
            ->notContains('#0 ')
            ->notContains('Exception');
        # The files share one buf input, so the failure leaves the others ungenerated as well.
        Assert::same($project->files('generated'), []);
    }

    public function reportsMissingAndOutsideProtoFiles(): void
    {
        $project = $this->project([
            'services' => ['proto/shop/order.proto', 'proto/nope.proto', '', 'outside/stray.proto'],
        ]);

        $result = $project->generate(['--no-ansi']);

        Assert::same($result->exitCode, 1, $result->output);
        Assert::string($result->output)->ignoringLineEndings()
            ->contains("Proto file `proto/nope.proto` not found.\n")
            ->contains("Proto file `` not found.\n")
            ->matchesRegex('/^Proto file `.*stray\.proto` is outside of the services base path `.*proto`\.$/m')
            ->contains("Generators were skipped because of the errors above.\n");
        Assert::array($project->files('generated'))
            ->contains('generated/Shop/Order.php')
            ->contains('generated/Common/Money.php');
    }

    public function stylesOutputOnlyWithAnsi(): void
    {
        $project = $this->project([
            'services' => ['proto/legacy/broken.proto'],
        ]);

        $styled = $project->generate(['--ansi']);
        $plain = $project->generate(['--no-ansi']);

        Assert::string($styled->output)->contains("\e[");
        Assert::string($plain->output)
            ->notContains("\e")
            ->same((string) \preg_replace('/\e\[[\d;]*m/', '', $styled->output));
    }

    public function usesUserTemplateAsIs(): void
    {
        $project = $this->project([
            'bufTemplate' => 'buf.gen.yaml',
            'generatedPath' => 'generated-user',
        ]);
        $out = 'generated-user';
        $project->write('buf.gen.yaml', \implode("\n", [
            'version: v2',
            'plugins:',
            '  - protoc_builtin: php',
            '    protoc_path: ' . GenerateProject::binary('protoc'),
            "    out: $out",
            '  - local: ' . GenerateProject::binary('protoc-gen-php-grpc'),
            "    out: $out",
        ]));

        $result = $project->generate(['--no-ansi']);

        Assert::same($result->exitCode, 0, $result->output);
        # The template's own relative `out`, resolved from the root: no namespace cut, no imported files.
        Assert::same($project->files($out), [
            'generated-user/Acceptance/GPBMetadata/Shop/Order.php',
            'generated-user/Acceptance/Shop/Order.php',
            'generated-user/Acceptance/Shop/OrderServiceInterface.php',
            'generated-user/Acceptance/Shop/PlaceOrderRequest.php',
            'generated-user/AcceptanceExtra/GPBMetadata/Notes/Note.php',
            'generated-user/AcceptanceExtra/Notes/Note.php',
            'generated-user/AcceptanceExtra/Notes/NoteServiceInterface.php',
        ]);
        Assert::string($result->output)->ignoringLineEndings()
            ->contains('• generated-user/Acceptance/Shop/Order.php')
            ->notContains('Classes outside the base namespace');
    }

    public function reportsMissingBuf(): void
    {
        $project = $this->project(['bufPath' => 'missing/buf']);

        $result = $project->generate(['--no-ansi']);

        Assert::same($result->exitCode, 1, $result->output);
        Assert::string($result->output)->ignoringLineEndings()
            ->matchesRegex('#^The `buf` binary `.*missing[\\\\/]buf` set in `bufPath` was not found\.#m')
            ->contains('vendor/bin/dload get buf')
            ->notContains('Stack trace')
            ->notContains('Compiling');
        Assert::same($project->files('generated'), []);
    }

    /**
     * The output with the project root replaced: the `Compiling` lines print absolute paths.
     */
    private static function rootless(ProcessResult $result, GenerateProject $project): string
    {
        return \str_replace([(string) \realpath($project->root), (string) Path::create($project->root), $project->root], '<root>', $result->output);
    }

    /**
     * @param array<non-empty-string, mixed> $config
     */
    private function project(array $config = []): GenerateProject
    {
        return $this->projects[] = GenerateProject::create($config);
    }
}
