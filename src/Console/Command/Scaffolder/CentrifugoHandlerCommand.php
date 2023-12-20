<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Console\Command\Scaffolder;

use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Attribute\Question;
use Spiral\RoadRunnerBridge\Scaffolder\Declaration\CentrifugoHandlerDeclaration;
use Spiral\Scaffolder\Command\AbstractCommand;

#[AsCommand(name: 'create:centrifugo-handler', description: 'Create Centrifugo handler declaration')]
final class CentrifugoHandlerCommand extends AbstractCommand
{
    #[Argument(description: 'Centrifugo handler name')]
    #[Question(question: 'What would you like to name the Centrifugo handler?')]
    private string $name;

    #[Option(shortcut: 'c', description: 'Optional comment to add as class header')]
    private ?string $comment = null;

    #[Option(description: 'Optional, specify a custom namespace')]
    private ?string $namespace = null;

    #[Option(shortcut: 't', description: 'Service type [connect, subscribe, rpc, refresh, publish]')]
    private string $type = 'connect';

    #[Option(name: 'with-api', shortcut: 'a', description: 'With API dependency')]
    private bool $withApi = false;

    public function perform(): int
    {
        $declaration = $this->createDeclaration(CentrifugoHandlerDeclaration::class, [
            'withApi' => $this->withApi,
        ]);

        $type = match ($this->type) {
            'connect' => [
                'request' => \RoadRunner\Centrifugo\Request\Connect::class,
                'use' => [
                    \RoadRunner\Centrifugo\Payload\ConnectResponse::class,
                ],
                'body' => $this->createConnectHandlerBody(),
            ],
            'subscribe' => [
                'request' => \RoadRunner\Centrifugo\Request\Subscribe::class,
                'use' => [
                    \RoadRunner\Centrifugo\Payload\SubscribeResponse::class,
                ],
                'body' => $this->createSubscribeHandlerBody(),
            ],
            'rpc' => [
                'request' => \RoadRunner\Centrifugo\Request\RPC::class,
                'use' => [
                    \RoadRunner\Centrifugo\Payload\RPCResponse::class,
                ],
                'body' => $this->createRpcHandlerBody(),
            ],
            'refresh' => [
                'request' => \RoadRunner\Centrifugo\Request\Refresh::class,
                'use' => [
                    \RoadRunner\Centrifugo\Payload\RefreshResponse::class,
                ],
                'body' => $this->createRefreshHandlerBody(),
            ],
            'publish' => [
                'request' => \RoadRunner\Centrifugo\Request\Publish::class,
                'use' => [
                    \RoadRunner\Centrifugo\Payload\PublishResponse::class,
                ],
                'body' => $this->createPublishHandlerBody(),
            ],
            default => throw new \InvalidArgumentException('Invalid service type'),
        };

        $declaration->setType($type);

        $this->writeDeclaration($declaration);

        return self::SUCCESS;
    }

    private function createConnectHandlerBody(): string
    {
        return <<<'PHP'
try {
    $request->respond(
        new ConnectResponse(
            user: '', // User ID
            channels: [
                // List of channels to subscribe to on connect to Centrifugo
                // 'public',
            ],
        )
    );
} catch (\Throwable $e) {
    $request->error($e->getCode(), $e->getMessage());
}
PHP;
    }

    private function createSubscribeHandlerBody(): string
    {
        return <<<'PHP'
try {
    // Here you can check if user is allowed to subscribe to requested channel
    if ($request->channel !== 'public') {
        $request->disconnect('403', 'Channel is not allowed.');
        return;
    }

    $request->respond(
        new SubscribeResponse()
    );
} catch (\Throwable $e) {
    $request->error($e->getCode(), $e->getMessage());
}
PHP;
    }

    private function createRpcHandlerBody(): string
    {
        return <<<'PHP'
$result = match ($request->method) {
    'ping' => ['pong' => 'pong', 'code' => 200],
    default => ['error' => 'Not found', 'code' => 404]
};

try {
    $request->respond(
        new RPCResponse(
            data: $result
        )
    );
} catch (\Throwable $e) {
    $request->error($e->getCode(), $e->getMessage());
}
PHP;
    }

    private function createRefreshHandlerBody(): string
    {
        return <<<'PHP'
try {
    $request->respond(
        new RefreshResponse(...)
    );
} catch (\Throwable $e) {
    $request->error($e->getCode(), $e->getMessage());
}
PHP;
    }

    private function createPublishHandlerBody(): string
    {
        return <<<'PHP'
try {
    $request->respond(
        new PublishResponse(...)
    );
} catch (\Throwable $e) {
    $request->error($e->getCode(), $e->getMessage());
}
PHP;
    }
}
