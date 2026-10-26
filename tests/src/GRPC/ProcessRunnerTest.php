<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use Spiral\RoadRunnerBridge\GRPC\Exception\CompileException;
use Spiral\RoadRunnerBridge\GRPC\Internal\ProcessRunner;
use Testo\Assert;
use Testo\Expect;
use Testo\Test;

#[Test]
final class ProcessRunnerTest
{
    public function successReturnsErrorOutput(): void
    {
        $warnings = (new ProcessRunner())->run([
            \PHP_BINARY, '-r', 'echo "out"; fwrite(STDERR, " a \"quoted\" warning ");',
        ]);

        Assert::same($warnings, 'a "quoted" warning');
    }

    public function successWithoutErrorOutput(): void
    {
        Assert::same((new ProcessRunner())->run([\PHP_BINARY, '-r', 'echo "out";']), '');
    }

    public function nonZeroExitCode(): void
    {
        Expect::exception(CompileException::class)->withMessageContaining('broken proto')->withCode(3);

        (new ProcessRunner())->run([\PHP_BINARY, '-r', 'fwrite(STDERR, "broken proto"); exit(3);']);
    }

    public function nonZeroExitCodeWithoutErrorOutput(): void
    {
        Expect::exception(CompileException::class)->withMessageContaining('exited with code 2. details');

        (new ProcessRunner())->run([\PHP_BINARY, '-r', 'echo "details"; exit(2);']);
    }
}
