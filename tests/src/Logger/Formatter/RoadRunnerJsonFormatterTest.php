<?php

declare(strict_types=1);

namespace Spiral\Tests\Logger\Formatter;

use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use Spiral\RoadRunnerBridge\Logger\Formatter\RoadRunnerJsonFormatter;
use Spiral\RoadRunnerBridge\Logger\RoadRunnerLogsMode;
use Spiral\Tests\TestCase;

final class RoadRunnerJsonFormatterTest extends TestCase
{
    public function testProductionFormat(): void
    {
        $formatter = new RoadRunnerJsonFormatter('production_', RoadRunnerLogsMode::Production);

        $result = $formatter->format(
            new LogRecord(
                datetime: new \DateTimeImmutable('now'),
                channel: 'roadrunner',
                level: Level::Debug,
                message: 'test_message',
                context: ['context_foo' => 'bar'],
                extra: ['extra_foo' => 'bar'],
            ),
        );

        $result['ts'] = '_TS_';

        $expected = [
            'level' => 'debug',
            'ts' => '_TS_',
            'logger' => 'production_roadrunner',
            'msg' => 'test_message',
            'context_foo' => 'bar',
            'extra_foo' => 'bar',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testFormatFromArray(): void
    {
        $formatter = new RoadRunnerJsonFormatter('production_', RoadRunnerLogsMode::Production);

        $result = $formatter->format(
            [
                'datetime' => new \DateTimeImmutable('now'),
                'channel' => 'roadrunner',
                'level' => Logger::DEBUG,
                'level_name' => 'DEBUG',
                'message' => 'test_message',
                'context' => ['context_foo' => 'bar'],
                'extra' => ['extra_foo' => 'bar'],
            ],
        );

        $result['ts'] = '_TS_';

        $expected = [
            'level' => 'debug',
            'ts' => '_TS_',
            'logger' => 'production_roadrunner',
            'msg' => 'test_message',
            'context_foo' => 'bar',
            'extra_foo' => 'bar',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testDevelopmentFormat(): void
    {
        $formatter = new RoadRunnerJsonFormatter('dev_', RoadRunnerLogsMode::Development);

        $result = $formatter->format(
            new LogRecord(
                datetime: new \DateTimeImmutable('now'),
                channel: 'roadrunner',
                level: Level::Debug,
                message: 'test_message',
                context: ['context_foo' => 'bar'],
                extra: ['extra_foo' => 'bar'],
            ),
        );

        $result['ts'] = '_TS_';

        $expected = [
            'level' => 'DEBUG',
            'ts' => '_TS_',
            'logger' => 'dev_roadrunner',
            'msg' => 'test_message',
            'context_foo' => 'bar',
            'extra_foo' => 'bar',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testFormatWithException(): void
    {
        $formatter = new RoadRunnerJsonFormatter('dev_', RoadRunnerLogsMode::Development);
        $exception = new \Exception('test_exception', 0, new \Exception('previous_test_exception'));

        $result = $formatter->format(
            new LogRecord(
                datetime: new \DateTimeImmutable('now'),
                channel: 'roadrunner',
                level: Level::Debug,
                message: 'test_message',
                context: ['exception' => $exception],
                extra: ['extra_foo' => 'bar'],
            ),
        );

        $this->assertEquals('test_exception', $result['exception']['message']);
        $this->assertEquals('previous_test_exception', $result['exception']['previous']['message']);
        $this->assertCount(5, $result['exception']['trace']);
    }
}
