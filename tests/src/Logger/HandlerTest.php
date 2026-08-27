<?php

declare(strict_types=1);

namespace Spiral\Tests\Logger;

use Monolog\Level;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use RoadRunner\Logger\Logger;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\RoadRunnerBridge\Logger\RoadRunnerLogsMode;
use Spiral\Tests\TestCase;

/**
 * @coversDefaultClass \Spiral\RoadRunnerBridge\Logger\Handler
 */
final class HandlerTest extends TestCase
{
    public static function toRRWithOutFallbackDataProvider(): iterable
    {
        yield 'Channel name is default, log level is error' => [
            'default',
            'error',
        ];

        yield 'Channel name is queue, log level is info' => [
            'queue',
            'info',
        ];

        yield 'Channel name is queue, log level is info, prefix is app-' => [
            'queue',
            'info',
            'app-',
        ];
    }

    public static function toRRWithDevModeDataProvider(): iterable
    {
        yield 'Channel name is default, log level is error' => [
            'default',
            'error',
        ];

        yield 'Channel name is queue, log level is info' => [
            'queue',
            'info',
        ];
    }

    public static function dataLevelFilter(): iterable
    {
        $dt = new \DateTimeImmutable();

        yield [
            Level::Debug,
            new LogRecord($dt, 'ch', Level::Debug, 'msg'),
            1,
        ];
        yield [
            Level::Notice,
            new LogRecord($dt, 'ch', Level::Debug, 'msg'),
        ];
        yield [
            Level::Warning,
            new LogRecord($dt, 'ch', Level::Debug, 'msg'),
        ];
        yield [
            Level::Error,
            new LogRecord($dt, 'ch', Level::Debug, 'msg'),
        ];
        yield [
            Level::Critical,
            new LogRecord($dt, 'ch', Level::Debug, 'msg'),
        ];
        yield [
            Level::Alert,
            new LogRecord($dt, 'ch', Level::Debug, 'msg'),
        ];
        yield [
            Level::Emergency,
            new LogRecord($dt, 'ch', Level::Debug, 'msg'),
        ];


        yield [
            Level::Debug,
            new LogRecord($dt, 'ch', Level::Emergency, 'msg'),
            1,
        ];
        yield [
            Level::Notice,
            new LogRecord($dt, 'ch', Level::Emergency, 'msg'),
            1,
        ];
        yield [
            Level::Warning,
            new LogRecord($dt, 'ch', Level::Emergency, 'msg'),
            1,
        ];
        yield [
            Level::Error,
            new LogRecord($dt, 'ch', Level::Emergency, 'msg'),
            1,
        ];
        yield [
            Level::Critical,
            new LogRecord($dt, 'ch', Level::Emergency, 'msg'),
            1,
        ];
        yield [
            Level::Alert,
            new LogRecord($dt, 'ch', Level::Emergency, 'msg'),
            1,
        ];
        yield [
            Level::Emergency,
            new LogRecord($dt, 'ch', Level::Emergency, 'msg'),
            1,
        ];
    }

    /**
     * @covers ::write
     *
     * @dataProvider toRRWithOutFallbackDataProvider
     */
    public function testLoggerShouldSendDataToRR(string $channelName, string $logLevel, string $logPrefix = ''): void
    {
        $rpc = $this->createMock(RPCInterface::class);

        $rpc
            ->expects(self::once())
            ->method('withServicePrefix')
            ->with('app')
            ->willReturnSelf();

        $monolog = new Monolog($channelName);

        $monolog->setHandlers([
            new Handler(
                logger: new Logger($rpc),
                loggerPrefix: $logPrefix,
            ),
        ]);

        $rpc
            ->expects(self::once())
            ->method('call')
            ->with('Log', self::callback(static function (string $json) use ($channelName, $logLevel, $logPrefix): bool {
                $data = \json_decode($json, true);
                if (\json_last_error() !== JSON_ERROR_NONE) {
                    return false;
                }

                if (!isset($data['ts']) && !\is_int($data['ts'])) {
                    return false;
                }
                $data['ts'] = '_TS_';

                $expected = [
                    'level' => $logLevel,
                    'ts' => '_TS_',
                    'logger' => $logPrefix . $channelName,
                    'msg' => 'Log message',
                ];

                \ksort($data);
                \ksort($expected);

                return $data === $expected;
            }))
            ->willReturnSelf();

        self::assertTrue(\method_exists($monolog, $logLevel));
        \call_user_func([$monolog, $logLevel], 'Log message');
    }

    /**
     * @covers ::write
     *
     * @dataProvider toRRWithDevModeDataProvider
     */
    public function testLoggerShouldSendDataToRRWithTsAndLevelInDevMode(string $channelName, string $logLevel): void
    {
        $rpc = $this->createMock(RPCInterface::class);

        $rpc
            ->expects(self::once())
            ->method('withServicePrefix')
            ->with('app')
            ->willReturnSelf();

        $monolog = new Monolog($channelName);

        $monolog->setHandlers([
            new Handler(
                logger: new Logger($rpc),
                loggerMode: RoadRunnerLogsMode::Development,
            ),
        ]);

        $rpc
            ->expects(self::once())
            ->method('call')
            ->with('Log', self::callback(static function (string $json) use ($channelName, $logLevel): bool {
                $data = \json_decode($json, true);
                if (\json_last_error() !== JSON_ERROR_NONE) {
                    return false;
                }

                if (!isset($data['ts']) || !\is_string($data['ts'])) {
                    return false;
                }

                $dateFormat = \DateTimeInterface::RFC3339;
                $datetime = \DateTime::createFromFormat($dateFormat, $data['ts']);
                if (!$datetime || $datetime->format($dateFormat) != $data['ts']) {
                    return false;
                }

                $data['ts'] = '_TS_';

                $expected = [
                    'level' => \strtoupper($logLevel),
                    'ts' => '_TS_',
                    'logger' => $channelName,
                    'msg' => 'Log message',
                ];

                \ksort($data);
                \ksort($expected);

                return $data === $expected;
            }))
            ->willReturnSelf();

        self::assertTrue(\method_exists($monolog, $logLevel));
        \call_user_func([$monolog, $logLevel], 'Log message');
    }

    public function testLoggerShouldSendMessageWithContextToRR(string $channelName = 'default', string $logLevel = 'error'): void
    {
        $rpc = $this->createMock(RPCInterface::class);
        $rpc
            ->expects(self::once())
            ->method('withServicePrefix')
            ->with('app')
            ->willReturnSelf();

        $monolog = new Monolog($channelName);

        $monolog->setHandlers([
            new Handler(
                logger: new Logger($rpc),
                loggerMode: RoadRunnerLogsMode::Development,
            ),
        ]);

        $rpc
            ->expects(self::once())
            ->method('call')
            ->with('Log', self::callback(static function (string $json) use ($channelName, $logLevel): bool {
                $data = \json_decode($json, true);
                if (\json_last_error() !== JSON_ERROR_NONE) {
                    return false;
                }

                if (!isset($data['ts']) || !\is_string($data['ts'])) {
                    return false;
                }

                $data['ts'] = '_TS_';

                $expected = [
                    'level' => \strtoupper($logLevel),
                    'ts' => '_TS_',
                    'logger' => $channelName,
                    'msg' => 'Log message',
                    'foo' => 'bar',
                ];

                \ksort($data);
                \ksort($expected);

                return $data === $expected;
            }))
            ->willReturnSelf();

        self::assertTrue(\method_exists($monolog, $logLevel));
        \call_user_func([$monolog, $logLevel], 'Log message', ['foo' => 'bar']);
    }

    /**
     * @covers ::write
     */
    public function testLoggerShouldNotEscapeSlashesAndUnicode(): void
    {
        $rpc = $this->createMock(RPCInterface::class);
        $rpc
            ->expects(self::once())
            ->method('withServicePrefix')
            ->with('app')
            ->willReturnSelf();

        $monolog = new Monolog('http');

        $monolog->setHandlers([
            new Handler(
                logger: new Logger($rpc),
            ),
        ]);

        $rpc
            ->expects(self::once())
            ->method('call')
            ->with('Log', self::callback(static function (string $json): bool {
                // Forward slashes must stay as-is, without the default json_encode "\/" escaping.
                self::assertStringContainsString('"path":"/api/v1/news/tag"', $json);
                self::assertStringNotContainsString('\\/', $json);

                // Non-ASCII must stay readable, not \uXXXX escaped.
                self::assertStringContainsString('Привет', $json);
                self::assertStringNotContainsString('\\u', $json);

                return true;
            }))
            ->willReturnSelf();

        $monolog->info('Log message', ['path' => '/api/v1/news/tag', 'greeting' => 'Привет']);
    }

    /**
     * @dataProvider dataLevelFilter
     */
    public function testLevelFilter(Level $level, LogRecord $record, int $expectsCount = 0): void
    {
        $rpc = $this->createMock(RPCInterface::class);
        $rpc
            ->expects(self::once())
            ->method('withServicePrefix')
            ->with('app')
            ->willReturnSelf();
        $rpc
            ->expects(self::exactly($expectsCount))
            ->method('call');

        $handler = new Handler(new Logger($rpc), level: $level);
        $handler->handle($record);
    }
}
