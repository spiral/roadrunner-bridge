<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Spiral\Tests\Acceptance\Support\Http;
use Testo\Assert;

abstract class LoggerTestCase extends ServerTestCase
{
    /**
     * Requests `/log`, which logs a warning with the given message, and returns the line RoadRunner wrote for it.
     */
    protected static function logAndFind(string $message): string
    {
        $response = Http::request('GET', self::$server->httpUrl("/log?message=$message"));
        Assert::same($response?->body, 'ok', self::$server->log());

        $line = self::eventually(static function () use ($message): ?string {
            \preg_match('/^.*' . $message . '.*$/m', self::$server->log(), $matches);
            return $matches[0] ?? null;
        });
        Assert::true($line !== null, self::$server->log());

        return (string) $line;
    }
}
