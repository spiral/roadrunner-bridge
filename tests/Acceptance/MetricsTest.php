<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Spiral\Tests\Acceptance\Support\Http;
use Testo\Assert;
use Testo\Test;

#[Test]
final class MetricsTest extends ServerTestCase
{
    public function exportsCounterIncrementedFromWorker(): void
    {
        Http::request('GET', self::$server->httpUrl('/metrics/hit'));
        Http::request('GET', self::$server->httpUrl('/metrics/hit'));

        $scraped = Http::request('GET', self::$server->metricsUrl());

        Assert::same($scraped?->status, 200);
        Assert::string($scraped->body)->contains("\nacceptance_hits 2\n");
    }
}
