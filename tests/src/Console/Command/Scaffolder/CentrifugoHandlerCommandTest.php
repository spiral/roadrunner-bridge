<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Scaffolder;

use Spiral\Tests\TestCase;

final class CentrifugoHandlerCommandTest extends TestCase
{
    public function testScaffold(): void
    {
        $this->assertScaffolderCommandSame(
            command: 'create:centrifugo-handler',
            args: [
                'name' => 'sample',
                '--comment' => 'Sample Handler',
            ],
            expected: <<<'PHP'
<?php

declare(strict_types=1);

namespace Spiral\Testing\Endpoint\Centrifugo\Handler;

use RoadRunner\Centrifugo\Payload\ConnectResponse;
use RoadRunner\Centrifugo\Request\Connect;
use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;

/**
 * Sample Handler
 */
final class SampleHandler implements ServiceInterface
{
    /**
     * @param Connect $request
     */
    public function handle(RequestInterface $request): void
    {
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
    }
}

PHP,
            expectedFilename: 'app/src/Endpoint/Centrifugo/Handler/SampleHandler.php',
            expectedOutputStrings: [
                "SampleHandler' has been successfully written into 'app/src/Endpoint/Centrifugo/Handler/SampleHandler.php",
            ],
        );
    }

    public function testScaffoldSubscribe(): void
    {
        $this->assertScaffolderCommandSame(
            command: 'create:centrifugo-handler',
            args: [
                'name' => 'sample',
                '--type' => 'subscribe',
                '--comment' => 'Sample Handler',
            ],
            expected: <<<'PHP'
<?php

declare(strict_types=1);

namespace Spiral\Testing\Endpoint\Centrifugo\Handler;

use RoadRunner\Centrifugo\Payload\SubscribeResponse;
use RoadRunner\Centrifugo\Request\RequestInterface;
use RoadRunner\Centrifugo\Request\Subscribe;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;

/**
 * Sample Handler
 */
final class SampleHandler implements ServiceInterface
{
    /**
     * @param Subscribe $request
     */
    public function handle(RequestInterface $request): void
    {
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
    }
}

PHP,
            expectedFilename: 'app/src/Endpoint/Centrifugo/Handler/SampleHandler.php',
            expectedOutputStrings: [
                "SampleHandler' has been successfully written into 'app/src/Endpoint/Centrifugo/Handler/SampleHandler.php",
            ],
        );
    }

    public function testScaffoldRpc(): void
    {
        $this->assertScaffolderCommandSame(
            command: 'create:centrifugo-handler',
            args: [
                'name' => 'sample',
                '--type' => 'rpc',
                '--comment' => 'Sample Handler',
            ],
            expected: <<<'PHP'
<?php

declare(strict_types=1);

namespace Spiral\Testing\Endpoint\Centrifugo\Handler;

use RoadRunner\Centrifugo\Payload\RPCResponse;
use RoadRunner\Centrifugo\Request\RPC;
use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;

/**
 * Sample Handler
 */
final class SampleHandler implements ServiceInterface
{
    /**
     * @param RPC $request
     */
    public function handle(RequestInterface $request): void
    {
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
    }
}

PHP,
            expectedFilename: 'app/src/Endpoint/Centrifugo/Handler/SampleHandler.php',
            expectedOutputStrings: [
                "SampleHandler' has been successfully written into 'app/src/Endpoint/Centrifugo/Handler/SampleHandler.php",
            ],
        );
    }

    public function testScaffoldRefresh(): void
    {
        $this->assertScaffolderCommandSame(
            command: 'create:centrifugo-handler',
            args: [
                'name' => 'sample',
                '--type' => 'refresh',
                '--comment' => 'Sample Handler',
            ],
            expected: <<<'PHP'
<?php

declare(strict_types=1);

namespace Spiral\Testing\Endpoint\Centrifugo\Handler;

use RoadRunner\Centrifugo\Payload\RefreshResponse;
use RoadRunner\Centrifugo\Request\Refresh;
use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;

/**
 * Sample Handler
 */
final class SampleHandler implements ServiceInterface
{
    /**
     * @param Refresh $request
     */
    public function handle(RequestInterface $request): void
    {
        try {
            $request->respond(
                new RefreshResponse(...)
            );
        } catch (\Throwable $e) {
            $request->error($e->getCode(), $e->getMessage());
        }
    }
}

PHP,
            expectedFilename: 'app/src/Endpoint/Centrifugo/Handler/SampleHandler.php',
            expectedOutputStrings: [
                "SampleHandler' has been successfully written into 'app/src/Endpoint/Centrifugo/Handler/SampleHandler.php",
            ],
        );
    }

    public function testScaffoldPublish(): void
    {
        $this->assertScaffolderCommandSame(
            command: 'create:centrifugo-handler',
            args: [
                'name' => 'sample',
                '--type' => 'publish',
                '--comment' => 'Sample Handler',
            ],
            expected: <<<'PHP'
<?php

declare(strict_types=1);

namespace Spiral\Testing\Endpoint\Centrifugo\Handler;

use RoadRunner\Centrifugo\Payload\PublishResponse;
use RoadRunner\Centrifugo\Request\Publish;
use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;

/**
 * Sample Handler
 */
final class SampleHandler implements ServiceInterface
{
    /**
     * @param Publish $request
     */
    public function handle(RequestInterface $request): void
    {
        try {
            $request->respond(
                new PublishResponse(...)
            );
        } catch (\Throwable $e) {
            $request->error($e->getCode(), $e->getMessage());
        }
    }
}

PHP,
            expectedFilename: 'app/src/Endpoint/Centrifugo/Handler/SampleHandler.php',
            expectedOutputStrings: [
                "SampleHandler' has been successfully written into 'app/src/Endpoint/Centrifugo/Handler/SampleHandler.php",
            ],
        );
    }

    public function testScaffoldWithCustomNamespace(): void
    {
        $this->assertScaffolderCommandSame(
            command: 'create:centrifugo-handler',
            args: [
                'name' => 'sample',
                '--namespace' => 'Spiral\\Testing\\Endpoint\\Centrifugo\\Other',
            ],
            expected: <<<'PHP'
<?php

declare(strict_types=1);

namespace Spiral\Testing\Endpoint\Centrifugo\Other;

use RoadRunner\Centrifugo\Payload\ConnectResponse;
use RoadRunner\Centrifugo\Request\Connect;
use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;

final class SampleHandler implements ServiceInterface
{
    /**
     * @param Connect $request
     */
    public function handle(RequestInterface $request): void
    {
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
    }
}

PHP,
            expectedFilename: 'app/src/Endpoint/Centrifugo/Other/SampleHandler.php',
            expectedOutputStrings: [
                "SampleHandler' has been successfully written into 'app/src/Endpoint/Centrifugo/Other/SampleHandler.php",
            ],
        );
    }

    public function testScaffoldWithApi(): void
    {
        $this->assertScaffolderCommandSame(
            command: 'create:centrifugo-handler',
            args: [
                'name' => 'sample',
                '--with-api' => true,
                '--namespace' => 'Spiral\\Testing\\Endpoint\\Centrifugo\\Other',
            ],
            expected: <<<'PHP'
<?php

declare(strict_types=1);

namespace Spiral\Testing\Endpoint\Centrifugo\Other;

use RoadRunner\Centrifugo\CentrifugoApiInterface;
use RoadRunner\Centrifugo\Payload\ConnectResponse;
use RoadRunner\Centrifugo\Request\Connect;
use RoadRunner\Centrifugo\Request\RequestInterface;
use Spiral\RoadRunnerBridge\Centrifugo\ServiceInterface;

final class SampleHandler implements ServiceInterface
{
    public function __construct(
        private readonly CentrifugoApiInterface $api,
    ) {
    }

    /**
     * @param Connect $request
     */
    public function handle(RequestInterface $request): void
    {
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
    }
}

PHP,
            expectedFilename: 'app/src/Endpoint/Centrifugo/Other/SampleHandler.php',
            expectedOutputStrings: [
                "SampleHandler' has been successfully written into 'app/src/Endpoint/Centrifugo/Other/SampleHandler.php",
            ],
        );
    }
}
