<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\GRPC\Internal;

use Spiral\RoadRunnerBridge\GRPC\Exception\CompileException;

/**
 * Runs a command without a shell.
 *
 * @internal
 */
final class ProcessRunner
{
    /**
     * @param non-empty-list<string> $command
     *
     * @return string Error output of a successful run: buf and plugins print warnings there.
     *
     * @throws CompileException When the command cannot be started or exits with a non-zero code.
     */
    public function run(array $command): string
    {
        // Files instead of pipes: reading one pipe to the end blocks while the process fills the other.
        $stdout = \tmpfile();
        $stderr = \tmpfile();
        if ($stdout === false || $stderr === false) {
            throw new CompileException('Unable to create temporary files for the process output.');
        }

        try {
            $process = \proc_open($command, [1 => $stdout, 2 => $stderr], $pipes);
            if ($process === false) {
                throw new CompileException(\sprintf('Unable to run `%s`.', $command[0]));
            }

            $exitCode = \proc_close($process);

            $output = \trim($this->read($stdout));
            $errors = \trim($this->read($stderr));
        } finally {
            \fclose($stdout);
            \fclose($stderr);
        }

        if ($exitCode !== 0) {
            throw new CompileException(
                $errors !== '' ? $errors : \sprintf('`%s` exited with code %d. %s', $command[0], $exitCode, $output),
                $exitCode,
            );
        }

        return $errors;
    }

    /**
     * @param resource $stream
     */
    private function read($stream): string
    {
        \rewind($stream);

        return (string) \stream_get_contents($stream);
    }
}
