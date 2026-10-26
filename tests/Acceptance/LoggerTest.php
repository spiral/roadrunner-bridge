<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Spiral\Tests\Acceptance\Support\Http;
use Testo\Assert;
use Testo\Test;

#[Test]
final class LoggerTest extends ServerTestCase
{
    public function writesApplicationLogThroughRoadRunner(): void
    {
        $message = 'acceptance-' . \bin2hex(\random_bytes(4));

        $response = Http::request('GET', self::$server->httpUrl("/log?message=$message"));
        Assert::same($response?->body, 'ok', self::$server->log());

        $logged = self::eventually(
            static fn(): ?bool => \str_contains(self::$server->log(), $message) ? true : null,
        );

        Assert::true($logged === true, self::$server->log());
    }
}
