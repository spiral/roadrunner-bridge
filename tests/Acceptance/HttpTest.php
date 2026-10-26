<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Spiral\Tests\Acceptance\Support\Http;
use Testo\Assert;
use Testo\Test;

#[Test]
final class HttpTest extends ServerTestCase
{
    public function servesGetRequest(): void
    {
        $response = Http::request('GET', self::$server->httpUrl('/hello?name=Spiral'));

        Assert::same($response?->status, 200);
        Assert::same($response->body, 'Hello, Spiral');
        Assert::same($response->header('X-Method'), 'GET');
    }

    public function passesRequestBodyAndHeaders(): void
    {
        $body = \json_encode(['answer' => 42]);

        $response = Http::request('POST', self::$server->httpUrl('/echo'), $body, ['Content-Type' => 'application/json']);

        Assert::same($response?->status, 200);
        Assert::same($response->body, $body);
        Assert::same($response->header('Content-Type'), 'application/json');
    }

    public function returnsHandlerStatus(): void
    {
        $response = Http::request('GET', self::$server->httpUrl('/missing'));

        Assert::same($response?->status, 404);
    }
}
