<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Tcp;

use Spiral\Core\InterceptableCore;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\Tcp\TcpWorker;
use Spiral\RoadRunner\Worker;
use Spiral\RoadRunner\WorkerInterface;
use Spiral\RoadRunnerBridge\Config\TcpConfig;
use Spiral\RoadRunnerBridge\Tcp\Interceptor\RegistryInterface;
use Spiral\RoadRunnerBridge\Tcp\Response\CloseConnection;

final class Server
{
    public function __construct(
        private readonly TcpConfig $config,
        private readonly RegistryInterface $registry,
        private readonly TcpServerHandler $handler
    ) {
    }

    public function serve(WorkerInterface $worker = null, callable $finalize = null): void
    {
        $worker ??= Worker::create();
        $tcpWorker = new TcpWorker($worker);

        while ($request = $tcpWorker->waitRequest()) {
            try {
                $core = $this->createHandler($request->server);
                $response = $core->callAction($request->server, 'handle', ['request' => $request]);
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

    private function createHandler(string $server): InterceptableCore
    {
        $core = new InterceptableCore($this->handler);

        foreach ($this->registry->getInterceptors($server) as $interceptor) {
            $core->addInterceptor($interceptor);
        }

        return $core;
    }
}
