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

use RoadRunner\Centrifugo\CentrifugoApiInterface;

/**
 * Sample Handler
 */
final class SampleHandler
{
    public function __construct(
        private readonly CentrifugoApiInterface $api,
    ) {
    }

    public function handle(): void
    {
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

use RoadRunner\Centrifugo\CentrifugoApiInterface;

final class SampleHandler
{
    public function __construct(
        private readonly CentrifugoApiInterface $api,
    ) {
    }

    public function handle(): void
    {
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
