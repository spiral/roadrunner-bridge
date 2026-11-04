<?php

declare(strict_types=1);

namespace Spiral\Tests\Logger;

use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use RoadRunner\Logger\Logger;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunnerBridge\Logger\RawHandler;
use Spiral\RoadRunnerBridge\Logger\RoadRunnerLogsMode;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Data\DataSet;
use Testo\Expect;
use Testo\Test;

final class RawHandlerTest extends TestCase
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

    #[Covers(RawHandler::class, 'write')]
    #[Test]
    #[DataProvider('toRRWithOutFallbackDataProvider')]
    public function loggerShouldSendDataToRR(string $channelName, string $logLevel, string $logPrefix = ''): void
    {
        $rpc = \Mockery::mock(RPCInterface::class);

        $rpc->shouldReceive('withServicePrefix')->once()->with('app', \Mockery::andAnyOtherArgs())->andReturnSelf();

        $monolog = new Monolog($channelName);

        $monolog->setHandlers([
            new RawHandler(
                logger: new Logger($rpc),
                loggerPrefix: $logPrefix,
            ),
        ]);

        $rpc->shouldReceive('call')->once()->with('Log', \Mockery::on(static function (string $json) use ($channelName, $logLevel, $logPrefix): bool {
            $data = \json_decode($json, true);
            if (\json_last_error() !== JSON_ERROR_NONE) {
                return false;
            }

            if (!isset($data['ts']) || !\is_string($data['ts']) || !\ctype_digit($data['ts'])) {
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
        }), \Mockery::andAnyOtherArgs())->andReturnSelf();

        Assert::true(\method_exists($monolog, $logLevel));
        \call_user_func([$monolog, $logLevel], 'Log message');
    }

    #[Covers(RawHandler::class, 'write')]
    #[Test]
    #[DataProvider('toRRWithDevModeDataProvider')]
    public function loggerShouldSendDataToRRWithTsAndLevelInDevMode(string $channelName, string $logLevel): void
    {
        $rpc = \Mockery::mock(RPCInterface::class);

        $rpc->shouldReceive('withServicePrefix')->once()->with('app', \Mockery::andAnyOtherArgs())->andReturnSelf();

        $monolog = new Monolog($channelName);

        $monolog->setHandlers([
            new RawHandler(
                logger: new Logger($rpc),
                loggerMode: RoadRunnerLogsMode::Development,
            ),
        ]);

        $rpc->shouldReceive('call')->once()->with('Log', \Mockery::on(static function (string $json) use ($channelName, $logLevel): bool {
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
        }), \Mockery::andAnyOtherArgs())->andReturnSelf();

        Assert::true(\method_exists($monolog, $logLevel));
        \call_user_func([$monolog, $logLevel], 'Log message');
    }

    #[Test]
    public function loggerShouldSendMessageWithContextToRR(string $channelName = 'default', string $logLevel = 'error'): void
    {
        $rpc = \Mockery::mock(RPCInterface::class);
        $rpc->shouldReceive('withServicePrefix')->once()->with('app', \Mockery::andAnyOtherArgs())->andReturnSelf();

        $monolog = new Monolog($channelName);

        $monolog->setHandlers([
            new RawHandler(
                logger: new Logger($rpc),
                loggerMode: RoadRunnerLogsMode::Development,
            ),
        ]);

        $rpc->shouldReceive('call')->once()->with('Log', \Mockery::on(static function (string $json) use ($channelName, $logLevel): bool {
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
        }), \Mockery::andAnyOtherArgs())->andReturnSelf();

        Assert::true(\method_exists($monolog, $logLevel));
        \call_user_func([$monolog, $logLevel], 'Log message', ['foo' => 'bar']);
    }

    #[Covers(RawHandler::class, 'write')]
    #[Test]
    public function loggerShouldNotEscapeSlashesAndUnicode(): void
    {
        $rpc = \Mockery::mock(RPCInterface::class);
        $rpc->shouldReceive('withServicePrefix')->once()->with('app', \Mockery::andAnyOtherArgs())->andReturnSelf();

        $monolog = new Monolog('http');

        $monolog->setHandlers([
            new RawHandler(
                logger: new Logger($rpc),
            ),
        ]);

        $rpc->shouldReceive('call')->once()->with('Log', \Mockery::on(static function (string $json): bool {
            // Forward slashes must stay as-is, without the default json_encode "\/" escaping.
            Assert::string($json)->contains('"path":"/api/v1/news/tag"')->notContains('\\/')->contains('Привет')->notContains('\\u');

            return true;
        }), \Mockery::andAnyOtherArgs())->andReturnSelf();

        $monolog->info('Log message', ['path' => '/api/v1/news/tag', 'greeting' => 'Привет']);
    }

    #[Test]
    #[DataSet([
        RoadRunnerLogsMode::Production,
        '{"level":"warning","ts":"1793784005123456000","logger":"app-roadrunner","msg":"Payment failed","order":{"id":7}}',
    ], 'production')]
    #[DataSet([
        RoadRunnerLogsMode::Development,
        '{"level":"WARNING","ts":"2026-11-04T13:20:05+04:00","logger":"app-roadrunner","msg":"Payment failed","order":{"id":7}}',
    ], 'development')]
    public function writesTheLineOfBridge4(RoadRunnerLogsMode $mode, string $line): void
    {
        $rpc = \Mockery::mock(RPCInterface::class);
        $rpc->shouldReceive('withServicePrefix')->with('app')->andReturnSelf();
        $rpc->shouldReceive('call')->once()->with('Log', $line . \PHP_EOL)->andReturnSelf();

        (new RawHandler(new Logger($rpc), 'app-', $mode))->handle(new LogRecord(
            datetime: new \DateTimeImmutable('2026-11-04 13:20:05.123456', new \DateTimeZone('+04:00')),
            channel: 'roadrunner',
            level: Level::Warning,
            message: 'Payment failed',
            context: ['order' => ['id' => 7]],
        ));
    }

    #[Test]
    public function sendsTheStringOfACustomFormatterAsIs(): void
    {
        $rpc = \Mockery::mock(RPCInterface::class);
        $rpc->shouldReceive('withServicePrefix')->with('app')->andReturnSelf();
        $rpc->shouldReceive('call')->once()->with('Log', 'hello' . \PHP_EOL)->andReturnSelf();

        $handler = new RawHandler(new Logger($rpc));
        $handler->setFormatter(new LineFormatter('%message%'));
        $handler->handle(new LogRecord(new \DateTimeImmutable(), 'ch', Level::Info, 'hello'));
    }

    #[Test]
    public function rejectsTheNativeMode(): void
    {
        $rpc = \Mockery::mock(RPCInterface::class);
        $rpc->shouldReceive('withServicePrefix')->with('app')->andReturnSelf();

        Expect::exception(\InvalidArgumentException::class);

        new RawHandler(new Logger($rpc), loggerMode: RoadRunnerLogsMode::Native);
    }

    #[Test]
    #[DataProvider('dataLevelFilter')]
    public function levelFilter(Level $level, LogRecord $record, int $expectsCount = 0): void
    {
        $rpc = \Mockery::mock(RPCInterface::class);
        $rpc->shouldReceive('withServicePrefix')->once()->with('app', \Mockery::andAnyOtherArgs())->andReturnSelf();
        $rpc->shouldReceive('call')->times($expectsCount);

        $handler = new RawHandler(new Logger($rpc), level: $level);
        $handler->handle($record);
    }
}
