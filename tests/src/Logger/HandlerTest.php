<?php

declare(strict_types=1);

namespace Spiral\Tests\Logger;

use Monolog\Logger as Monolog;
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
    public static function handlerFactory(RPCInterface $rpc, ?string $logPrefix = null, ?RoadRunnerLogsMode $logsMode = null): Handler
    {
        if ($logPrefix !== null && $logsMode !== null) {
            return new Handler(
                new Logger($rpc),
                '%message% foo',
                $logPrefix,
                $logsMode,
            );
        } elseif ($logPrefix !== null) {
            return new Handler(
                new Logger($rpc),
                '%message% foo',
                loggerPrefix: $logPrefix,
            );
        } elseif ($logsMode !== null) {
            return new Handler(
                new Logger($rpc),
                '%message% foo',
                loggerMode: $logsMode,
            );
        }

        return new Handler(
            new Logger($rpc),
            '%message% foo',
        );

    }

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

    /**
     * @covers ::write
     *
     * @dataProvider toRRWithOutFallbackDataProvider
     */
    public function testLoggerShouldSendDataToRR(string $channelName, string $logLevel, ?string $logPrefix = null): void
    {
        $rpc = $this->createMock(RPCInterface::class);

        $rpc
            ->expects(self::once())
            ->method('withServicePrefix')
            ->with('app')
            ->willReturnSelf();

        $monolog = new Monolog($channelName);

        $monolog->setHandlers([self::HandlerFactory($rpc, $logPrefix)]);

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

        $monolog->setHandlers([self::handlerFactory($rpc, logsMode: RoadRunnerLogsMode::Development)]);

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

        $monolog->setHandlers([self::handlerFactory($rpc, logsMode: RoadRunnerLogsMode::Development)]);

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
}
