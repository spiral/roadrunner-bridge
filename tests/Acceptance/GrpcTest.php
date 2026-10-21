<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance;

use Spiral\App\GRPC\Ping\PingRequest;
use Spiral\Tests\Acceptance\Support\Http;
use Testo\Assert;
use Testo\Test;

#[Test]
final class GrpcTest extends ServerTestCase
{
    public function callsUnaryMethod(): void
    {
        $message = (new PingRequest())->setFrom('acceptance')->serializeToString();

        $response = Http::request(
            'POST',
            self::$server->grpcUrl('/ping.dto.PingService/Ping'),
            # gRPC frame: an uncompressed flag byte and the big-endian message length.
            "\0" . \pack('N', \strlen($message)) . $message,
            ['Content-Type' => 'application/grpc', 'TE' => 'trailers'],
            http2: true,
        );

        Assert::same($response?->status, 200, self::$server->log());
        Assert::same($response->header('grpc-status'), '0', self::$server->log());
        Assert::same($response->header('x-pinged-by'), 'acceptance');
    }

    public function reportsUnknownMethod(): void
    {
        $response = Http::request(
            'POST',
            self::$server->grpcUrl('/ping.dto.PingService/Missing'),
            "\0\0\0\0\0",
            ['Content-Type' => 'application/grpc', 'TE' => 'trailers'],
            http2: true,
        );

        # 12 is UNIMPLEMENTED.
        Assert::same($response?->header('grpc-status'), '12', self::$server->log());
    }
}
