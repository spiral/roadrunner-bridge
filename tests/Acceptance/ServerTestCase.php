<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Spiral\Tests\Acceptance\Support\RoadRunner;
use Testo\Lifecycle\AfterClass;
use Testo\Lifecycle\BeforeClass;

/**
 * Runs the tests of a class against one RoadRunner server.
 */
abstract class ServerTestCase
{
    protected static RoadRunner $server;

    #[BeforeClass]
    public static function startServer(): void
    {
        static::$server = RoadRunner::start();
    }

    #[AfterClass]
    public static function stopServer(): void
    {
        static::$server->stop();
    }

    /**
     * Polls until the condition returns a non-null value: RoadRunner hands work to other workers asynchronously.
     *
     * @template T
     * @param \Closure(): (T|null) $condition
     * @return T|null Null on timeout.
     */
    protected static function eventually(\Closure $condition, float $timeout = 10.0): mixed
    {
        $deadline = \microtime(true) + $timeout;
        do {
            $result = $condition();
            if ($result !== null) {
                return $result;
            }

            \usleep(100_000);
        } while (\microtime(true) < $deadline);

        return null;
    }
}
