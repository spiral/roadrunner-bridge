<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Spiral\Tests\Acceptance\Support\Http;
use Testo\Assert;
use Testo\Test;

#[Test]
final class CacheTest extends ServerTestCase
{
    public function storesValueInKeyValuePlugin(): void
    {
        $key = \bin2hex(\random_bytes(4));

        $stored = Http::request('GET', self::$server->httpUrl("/cache/set?key=$key&value=cached"));
        $read = Http::request('GET', self::$server->httpUrl("/cache/get?key=$key"));

        Assert::same($stored?->body, 'true', self::$server->log());
        Assert::same($read?->body, 'cached', self::$server->log());
    }

    public function returnsDefaultForMissingKey(): void
    {
        $read = Http::request('GET', self::$server->httpUrl('/cache/get?key=missing'));

        Assert::same($read?->body, '(missing)', self::$server->log());
    }
}
