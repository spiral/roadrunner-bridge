<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Testo\Assert;
use Testo\Test;

/**
 * `RR_LOGGER_MODE=production`: the bridge writes the JSON line of bridge 4.x and RoadRunner prints it as is.
 */
#[Test]
final class LoggerRawTest extends LoggerTestCase
{
    public function writesTheLineOfBridge4(): void
    {
        $message = 'acceptance-' . \bin2hex(\random_bytes(4));

        $line = self::logAndFind($message);

        $record = \json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
        Assert::array($record);
        Assert::same(\array_slice(\array_keys($record), 0, 4), ['level', 'ts', 'logger', 'msg']);
        Assert::same($record['level'], 'warning');
        Assert::string($record['ts'])->matchesRegex('/^\d{19}$/');
        Assert::same($record['logger'], 'roadrunner');
        Assert::same($record['msg'], $message);
        Assert::same($record['user'] ?? null, ['id' => 7]);
    }

    public function bypassesTheRoadRunnerLevel(): void
    {
        $message = 'acceptance-' . \bin2hex(\random_bytes(4));

        // `.rr.yaml` sets `logs.level: warn`, so a native info record would not be printed
        $line = self::logAndFind($message, 'info');

        Assert::same(\json_decode($line, true, flags: \JSON_THROW_ON_ERROR)['level'] ?? null, 'info');
    }

    #[\Override]
    protected static function environment(): array
    {
        return ['RR_LOGGER_MODE' => 'production'];
    }
}
