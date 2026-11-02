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

        $line = self::eventually(static function () use ($message): ?string {
            \preg_match('/^.*msg=' . $message . '.*$/m', self::$server->log(), $matches);
            return $matches[0] ?? null;
        });

        Assert::true($line !== null, self::$server->log());
        // `logs.mode: development` in .rr.yaml: RoadRunner renders the record as key=value pairs
        Assert::string((string) $line)
            ->contains('level=WARN')
            ->contains('logger=app')
            ->contains('channel=roadrunner')
            ->contains('user.id=7');
    }
}
