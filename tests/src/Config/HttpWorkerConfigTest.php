<?php

declare(strict_types=1);

namespace Spiral\Tests\Config;

use Spiral\RoadRunnerBridge\Config\HttpWorkerConfig;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Test;

final class HttpWorkerConfigTest extends TestCase
{
    #[Test]
    public function serverIsNotPopulatedByDefault(): void
    {
        Assert::false((new HttpWorkerConfig())->shouldPopulateServer());
        Assert::false($this->getContainer()->get(HttpWorkerConfig::class)->shouldPopulateServer());
    }

    #[Test]
    public function populateServer(): void
    {
        Assert::true((new HttpWorkerConfig(['populateServer' => true]))->shouldPopulateServer());
    }
}
