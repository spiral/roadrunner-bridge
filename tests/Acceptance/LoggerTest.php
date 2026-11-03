<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Testo\Assert;
use Testo\Test;

/**
 * `logs.mode: development` (the `.rr.yaml` default): RoadRunner renders a record as key=value pairs.
 */
#[Test]
final class LoggerTest extends LoggerTestCase
{
    public function writesApplicationLogThroughRoadRunner(): void
    {
        $message = 'acceptance-' . \bin2hex(\random_bytes(4));

        $line = self::logAndFind($message);

        Assert::string($line)
            ->contains('level=WARN')
            ->contains('logger=app')
            ->contains('channel=roadrunner')
            ->contains('user.id=7')
            ->contains('context.msg=from-context');
    }
}
