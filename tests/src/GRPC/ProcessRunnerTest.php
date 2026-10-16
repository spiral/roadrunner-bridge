<?php

declare(strict_types=1);

namespace Spiral\Tests\GRPC;

use PHPUnit\Framework\TestCase;
use Spiral\RoadRunnerBridge\GRPC\Exception\CompileException;
use Spiral\RoadRunnerBridge\GRPC\Internal\ProcessRunner;

final class ProcessRunnerTest extends TestCase
{
    public function testSuccessReturnsErrorOutput(): void
    {
        $warnings = (new ProcessRunner())->run([
            \PHP_BINARY, '-r', 'echo "out"; fwrite(STDERR, " a \"quoted\" warning ");',
        ]);

        $this->assertSame('a "quoted" warning', $warnings);
    }

    public function testSuccessWithoutErrorOutput(): void
    {
        $this->assertSame('', (new ProcessRunner())->run([\PHP_BINARY, '-r', 'echo "out";']));
    }

    public function testNonZeroExitCode(): void
    {
        $this->expectException(CompileException::class);
        $this->expectExceptionMessage('broken proto');
        $this->expectExceptionCode(3);

        (new ProcessRunner())->run([\PHP_BINARY, '-r', 'fwrite(STDERR, "broken proto"); exit(3);']);
    }

    public function testNonZeroExitCodeWithoutErrorOutput(): void
    {
        $this->expectException(CompileException::class);
        $this->expectExceptionMessage('exited with code 2. details');

        (new ProcessRunner())->run([\PHP_BINARY, '-r', 'echo "details"; exit(2);']);
    }
}
