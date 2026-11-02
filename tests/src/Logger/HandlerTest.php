<?php

declare(strict_types=1);

namespace Spiral\Tests\Logger;

use Monolog\Level;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;
use RoadRunner\AppLogger\DTO\V1\LogAttrs;
use RoadRunner\AppLogger\DTO\V1\LogEntry;
use RoadRunner\Logger\Logger;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunnerBridge\Logger\Handler;
use Spiral\Tests\TestCase;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Test;

#[Covers(Handler::class)]
final class HandlerTest extends TestCase
{
    /** @var list<array{string, mixed}> */
    private array $calls = [];

    public static function levelMappingDataProvider(): iterable
    {
        yield 'debug' => [Level::Debug, 'DebugWithContext'];
        yield 'info' => [Level::Info, 'InfoWithContext'];
        yield 'notice' => [Level::Notice, 'InfoWithContext'];
        yield 'warning' => [Level::Warning, 'WarningWithContext'];
        yield 'error' => [Level::Error, 'ErrorWithContext'];
        yield 'critical' => [Level::Critical, 'ErrorWithContext'];
        yield 'alert' => [Level::Alert, 'ErrorWithContext'];
        yield 'emergency' => [Level::Emergency, 'ErrorWithContext'];
    }

    public static function dataLevelFilter(): iterable
    {
        $dt = new \DateTimeImmutable();

        yield [Level::Debug, new LogRecord($dt, 'ch', Level::Debug, 'msg'), 1];
        yield [Level::Notice, new LogRecord($dt, 'ch', Level::Debug, 'msg')];
        yield [Level::Emergency, new LogRecord($dt, 'ch', Level::Debug, 'msg')];
        yield [Level::Debug, new LogRecord($dt, 'ch', Level::Emergency, 'msg'), 1];
        yield [Level::Emergency, new LogRecord($dt, 'ch', Level::Emergency, 'msg'), 1];
    }

    #[Test]
    #[DataProvider('levelMappingDataProvider')]
    public function mapsMonologLevelToAppLoggerMethod(Level $level, string $method): void
    {
        $monolog = new Monolog('default', [new Handler($this->appLogger())]);

        $monolog->log($level, 'Log message');

        Assert::count($this->calls, 1);
        Assert::same($this->calls[0][0], $method);
        Assert::same($this->entry()->getMessage(), 'Log message');
    }

    #[Test]
    public function sendsChannelWithPrefix(): void
    {
        $monolog = new Monolog('queue', [new Handler($this->appLogger(), loggerPrefix: 'app-')]);

        $monolog->info('Log message');

        Assert::same($this->attributes(), ['channel' => 'app-queue']);
    }

    #[Test]
    public function sendsContextAndExtra(): void
    {
        $monolog = new Monolog('default', [new Handler($this->appLogger())]);
        $monolog->pushProcessor(static function (LogRecord $record): LogRecord {
            $record->extra['request_id'] = 'abc';
            return $record;
        });

        $monolog->info('Log message', ['foo' => 'bar', 'count' => 42]);

        Assert::same($this->attributes(), [
            'channel' => 'default',
            'foo' => 'bar',
            'count' => '42',
            'request_id' => 'abc',
        ]);
    }

    #[Test]
    public function flattensNestedMapsAndKeepsLists(): void
    {
        $monolog = new Monolog('default', [new Handler($this->appLogger())]);

        $monolog->info('Log message', [
            'user' => ['id' => 1, 'address' => ['city' => 'Москва']],
            'tags' => ['/api/v1', 'Привет'],
            'empty' => [],
        ]);

        Assert::same($this->attributes(), [
            'channel' => 'default',
            'user.id' => '1',
            'user.address.city' => 'Москва',
            'tags' => '["/api/v1","Привет"]',
            'empty' => '[]',
        ]);
    }

    #[Test]
    public function sendsExceptionAsSeparateAttributes(): void
    {
        $monolog = new Monolog('default', [new Handler($this->appLogger())]);
        $previous = new \LogicException('Cause');
        $exception = self::deepException(8, $previous);

        $monolog->error('Failed', ['exception' => $exception]);

        $attributes = $this->attributes();
        Assert::same($attributes['exception.class'], \RuntimeException::class);
        Assert::same($attributes['exception.message'], 'Boom');
        Assert::same($attributes['exception.code'], '42');
        Assert::same($attributes['exception.file'], $exception->getFile() . ':' . $exception->getLine());
        Assert::count(\json_decode($attributes['exception.trace'], true), 5);
        Assert::same($attributes['exception.previous.class'], \LogicException::class);
        Assert::same($attributes['exception.previous.message'], 'Cause');
    }

    #[Test]
    public function normalizesValuesJsonCannotEncode(): void
    {
        $monolog = new Monolog('default', [new Handler($this->appLogger())]);

        $object = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['id' => 5];
            }
        };

        $monolog->info('Log message', ['nan' => \NAN, 'list' => [1.5, \INF], 'object' => $object]);

        // Monolog keys a normalized object by its class
        Assert::same($this->attributes(), [
            'channel' => 'default',
            'nan' => 'NaN',
            'list' => '[1.5,"INF"]',
            'object.' . \Monolog\Utils::getClass($object) . '.id' => '5',
        ]);
    }

    #[Test]
    public function limitsNormalizationDepth(): void
    {
        $monolog = new Monolog('default', [new Handler($this->appLogger())]);
        $context = ['level' => 0];
        $context['self'] = &$context;

        $monolog->info('Log message', $context);

        Assert::count($this->calls, 1);
    }

    #[Test]
    public function movesKeysClashingWithRoadRunnerFields(): void
    {
        $monolog = new Monolog('default', [new Handler($this->appLogger())]);

        $monolog->info('Log message', ['msg' => 'a', 'time' => 'b', 'level' => 'c', 'logger' => 'd', 'channel' => 'e']);

        Assert::same($this->attributes(), [
            'channel' => 'default',
            'context.msg' => 'a',
            'context.time' => 'b',
            'context.level' => 'c',
            'context.logger' => 'd',
            'context.channel' => 'e',
        ]);
    }

    #[Test]
    public function resetResetsProcessors(): void
    {
        $handler = new Handler($this->appLogger());
        $processor = new class implements \Monolog\ResettableInterface {
            public int $resets = 0;

            public function __invoke(LogRecord $record): LogRecord
            {
                return $record;
            }

            public function reset(): void
            {
                ++$this->resets;
            }
        };
        $handler->pushProcessor($processor);

        $handler->reset();

        Assert::same($processor->resets, 1);
    }

    #[Test]
    public function processorsRunBeforeSending(): void
    {
        $handler = new Handler($this->appLogger());
        $handler->pushProcessor(static fn(LogRecord $record): LogRecord => $record->with(message: 'Processed'));

        $handler->handle(new LogRecord(new \DateTimeImmutable(), 'ch', Level::Info, 'msg'));

        Assert::same($this->entry()->getMessage(), 'Processed');
    }

    #[Test]
    public function bubbling(): void
    {
        $record = new LogRecord(new \DateTimeImmutable(), 'ch', Level::Info, 'msg');

        Assert::false((new Handler($this->appLogger()))->handle($record));
        Assert::true((new Handler($this->appLogger(), bubble: false))->handle($record));
    }

    #[Test]
    #[DataProvider('dataLevelFilter')]
    public function levelFilter(Level $level, LogRecord $record, int $expectsCount = 0): void
    {
        $handler = new Handler($this->appLogger(), level: $level);

        $handler->handle($record);

        Assert::count($this->calls, $expectsCount);
    }

    private static function deepException(int $depth, \Throwable $previous): \RuntimeException
    {
        return $depth === 0
            ? new \RuntimeException('Boom', 42, $previous)
            : self::deepException($depth - 1, $previous);
    }

    private function appLogger(): Logger
    {
        $rpc = \Mockery::mock(RPCInterface::class);
        $rpc->shouldReceive('withServicePrefix')->with('app')->andReturnSelf();
        $rpc->shouldReceive('withCodec')->andReturnSelf();
        $rpc->shouldReceive('call')->andReturnUsing(function (string $method, mixed $payload): mixed {
            $this->calls[] = [$method, $payload];
            return null;
        });

        return new Logger($rpc);
    }

    private function entry(): LogEntry
    {
        $entry = $this->calls[0][1] ?? null;
        Assert::instanceOf($entry, LogEntry::class);

        return $entry;
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        $result = [];
        /** @var LogAttrs $attr */
        foreach ($this->entry()->getLogAttrs() as $attr) {
            $result[$attr->getKey()] = $attr->getValue();
        }

        return $result;
    }
}
