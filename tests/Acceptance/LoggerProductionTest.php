<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Testo\Assert;
use Testo\Test;

/**
 * `logs.mode: production`: RoadRunner renders a record as one JSON object.
 */
#[Test]
final class LoggerProductionTest extends LoggerTestCase
{
    public function writesApplicationLogAsJson(): void
    {
        $message = 'acceptance-' . \bin2hex(\random_bytes(4));

        $line = self::logAndFind($message);

        $record = \json_decode($line, true, flags: \JSON_THROW_ON_ERROR);
        Assert::array($record);
        Assert::string($record['time'] ?? null);
        // RoadRunner writes a context `msg` as a second `msg` key, and a decoder keeps the last one
        Assert::same($record['msg'] ?? null, $message);
        Assert::same($record['level'] ?? null, 'WARN');
        Assert::same($record['logger'] ?? null, 'app');
        Assert::same($record['channel'] ?? null, 'roadrunner');
        Assert::same($record['user.id'] ?? null, '7');
        Assert::same($record['context.msg'] ?? null, 'from-context');
    }

    #[\Override]
    protected static function environment(): array
    {
        return ['RR_LOGS_MODE' => 'production'];
    }
}
