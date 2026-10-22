<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\Support;

use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;

/**
 * A RoadRunner binary serving `tests/Acceptance/App` on free local ports, with its own runtime directory.
 */
final class RoadRunner
{
    private const APP_DIR = __DIR__ . '/../App';
    private const START_TIMEOUT = 30.0;

    /**
     * @param resource $process
     * @param array<non-empty-string, int> $ports
     */
    private function __construct(
        private $process,
        public readonly string $runtime,
        private readonly array $ports,
        private readonly string $log,
    ) {}

    public static function start(): self
    {
        $root = \dirname(__DIR__, 3);
        $binary = $root . \DIRECTORY_SEPARATOR . (\PHP_OS_FAMILY === 'Windows' ? 'rr.exe' : 'rr');
        \is_file($binary) or throw new \RuntimeException(
            "RoadRunner binary not found at `$binary`: run `vendor/bin/dload get rr`.",
        );

        $runtime = $root . '/runtime/acceptance/' . \bin2hex(\random_bytes(4));
        \mkdir($runtime, 0777, true);
        $log = $runtime . '/rr.log';

        $ports = self::freePorts('RR_RPC_PORT', 'RR_HTTP_PORT', 'RR_GRPC_PORT', 'RR_METRICS_PORT');

        # .rr.yaml reads the ports and the PHP binary from the environment; the workers inherit it.
        $env = \array_merge(\getenv(), $ports, [
            'ACCEPTANCE_PHP' => \PHP_BINARY,
            'ACCEPTANCE_RUNTIME' => $runtime,
        ]);

        $process = \proc_open(
            [$binary, 'serve', '-c', '.rr.yaml', '-w', self::APP_DIR],
            [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            self::APP_DIR,
            $env,
        );
        \is_resource($process) or throw new \RuntimeException('Cannot start RoadRunner.');

        $server = new self($process, $runtime, $ports, $log);
        $server->waitUntilReady();

        return $server;
    }

    public function stop(): void
    {
        if (!\is_resource($this->process)) {
            return;
        }

        \proc_terminate($this->process);
        \proc_close($this->process);

        (new \Spiral\Files\Files())->deleteDirectory($this->runtime);
    }

    public function httpUrl(string $path): string
    {
        return "http://127.0.0.1:{$this->ports['RR_HTTP_PORT']}$path";
    }

    public function metricsUrl(): string
    {
        return "http://127.0.0.1:{$this->ports['RR_METRICS_PORT']}/metrics";
    }

    public function grpcUrl(string $path): string
    {
        return "http://127.0.0.1:{$this->ports['RR_GRPC_PORT']}$path";
    }

    public function rpc(): RPCInterface
    {
        return new RPC(Relay::create("tcp://127.0.0.1:{$this->ports['RR_RPC_PORT']}"));
    }

    public function log(): string
    {
        return (string) @\file_get_contents($this->log);
    }

    /**
     * @param non-empty-string ...$names
     * @return array<non-empty-string, int>
     */
    private static function freePorts(string ...$names): array
    {
        # Every socket stays open until all ports are taken: a closed one may hand the same port out again.
        $sockets = $ports = [];
        foreach ($names as $name) {
            $sockets[] = $socket = \stream_socket_server('tcp://127.0.0.1:0');
            $ports[$name] = (int) \substr(\strrchr(\stream_socket_get_name($socket, false), ':'), 1);
        }

        \array_map(\fclose(...), $sockets);

        return $ports;
    }

    private function waitUntilReady(): void
    {
        $deadline = \microtime(true) + self::START_TIMEOUT;
        do {
            if (!\proc_get_status($this->process)['running']) {
                $log = $this->log();
                $this->stop();
                throw new \RuntimeException("RoadRunner exited during start:\n" . $log);
            }

            # The plugins start listening independently of each other.
            if (Http::request('GET', $this->httpUrl('/hello'))?->status === 200
                && Http::request('GET', $this->metricsUrl())?->status === 200
                && Http::request('POST', $this->grpcUrl('/'), http2: true) !== null
            ) {
                return;
            }

            \usleep(200_000);
        } while (\microtime(true) < $deadline);

        $log = $this->log();
        $this->stop();
        throw new \RuntimeException("RoadRunner did not start in time:\n" . $log);
    }
}
