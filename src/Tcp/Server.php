<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp;

use Spiral\Core\InterceptableCore;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Tcp\TcpWorker;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\Config\TcpConfig;
use Spiral\RoadRunnerBridge\Tcp\Response\CloseConnection;

final class Server
{
    private TcpConfig $config;
    private InterceptableCore $core;

    public function __construct(TcpConfig $config, InterceptableCore $core)
    {
        $this->config = $config;
        $this->core = $core;
    }

    public function serve(WorkerInterface $worker = null, callable $finalize = null): void
    {
        $worker ??= Worker::create();
        $tcpWorker = new TcpWorker($worker);

        while ($request = $tcpWorker->waitRequest()) {
            try {
                $response = $this->core->callAction($request->server, 'handle', ['request' => $request]);
            } catch (\Throwable $e) {
                $worker->error($this->config->isDebugMode() ? (string) $e : $e->getMessage());
                $response = new CloseConnection();
            } finally {
                $tcpWorker->getWorker()->respond(
                    new Payload($response->getBody(), $response->getAction())
                );

                if ($finalize !== null) {
                    isset($e) ? $finalize($e) : $finalize();
                }
            }
        }
    }
}
