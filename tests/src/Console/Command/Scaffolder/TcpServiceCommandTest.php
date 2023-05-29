<?php

declare(strict_types=1);

namespace Spiral\Tests\Console\Command\Scaffolder;

use Spiral\Tests\TestCase;

final class TcpServiceCommandTest extends TestCase
{
    public function testScaffold(): void
    {
        $this->assertScaffolderCommandSame(
            command: 'create:tcp-service',
            args: [
                'name' => 'sample',
                '--comment' => 'Sample Service',
            ],
            expected: <<<'PHP'
<?php

declare(strict_types=1);

namespace Spiral\Testing\Endpoint\Tcp\Service;

use Spiral\RoadRunner\Tcp\Request;
use Spiral\RoadRunnerBridge\Tcp\Response\RespondMessage;
use Spiral\RoadRunnerBridge\Tcp\Response\ResponseInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;

/**
 * Sample Service
 */
final class SampleService implements ServiceInterface
{
    public function handle(Request $request): ResponseInterface
    {
        return new RespondMessage('some message', true);
    }
}

PHP,
            expectedFilename: 'app/src/Endpoint/Tcp/Service/SampleService.php',
            expectedOutputStrings: [
                "Declaration of 'SampleService' has been successfully written into 'app/src/Endpoint/Tcp/Service/SampleService.php",
            ],
        );
    }

    public function testScaffoldWithCustomNamespace(): void
    {
        $this->assertScaffolderCommandSame(
            command: 'create:tcp-service',
            args: [
                'name' => 'sample',
                '--namespace' => 'Spiral\\Testing\\Endpoint\\Tcp\\Other',
            ],
            expected: <<<'PHP'
<?php

declare(strict_types=1);

namespace Spiral\Testing\Endpoint\Tcp\Other;

use Spiral\RoadRunner\Tcp\Request;
use Spiral\RoadRunnerBridge\Tcp\Response\RespondMessage;
use Spiral\RoadRunnerBridge\Tcp\Response\ResponseInterface;
use Spiral\RoadRunnerBridge\Tcp\Service\ServiceInterface;

final class SampleService implements ServiceInterface
{
    public function handle(Request $request): ResponseInterface
    {
        return new RespondMessage('some message', true);
    }
}

PHP,
            expectedFilename: 'app/src/Endpoint/Tcp/Other/SampleService.php',
            expectedOutputStrings: [
                "Declaration of 'SampleService' has been successfully written into 'app/src/Endpoint/Tcp/Other/SampleService.php",
            ],
        );
    }
}
