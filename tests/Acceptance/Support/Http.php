<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\Support;

/**
 * A minimal cURL client: HTTP/1.1 for the HTTP plugin, cleartext HTTP/2 for gRPC.
 */
final class Http
{
    /**
     * @param array<non-empty-string, string> $headers
     * @return Response|null Null when the server could not be reached.
     */
    public static function request(
        string $method,
        string $url,
        string $body = '',
        array $headers = [],
        bool $http2 = false,
    ): ?Response {
        $responseHeaders = [];
        $curl = \curl_init($url);
        \curl_setopt_array($curl, [
            \CURLOPT_CUSTOMREQUEST => $method,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT => 10,
            \CURLOPT_HTTPHEADER => \array_map(
                static fn(string $name, string $value): string => "$name: $value",
                \array_keys($headers),
                $headers,
            ),
            # RoadRunner 3 speaks cleartext HTTP/2 with prior knowledge only, without an `Upgrade: h2c` round.
            \CURLOPT_HTTP_VERSION => $http2 ? \CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE : \CURL_HTTP_VERSION_1_1,
            # Called for the trailers as well, which is where gRPC puts its status.
            \CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = \explode(':', $line, 2);
                if (\count($parts) === 2) {
                    $responseHeaders[\strtolower(\trim($parts[0]))][] = \trim($parts[1]);
                }

                return \strlen($line);
            },
        ]);
        $body === '' or \curl_setopt($curl, \CURLOPT_POSTFIELDS, $body);

        $result = \curl_exec($curl);
        if ($result === false) {
            return null;
        }

        return new Response(\curl_getinfo($curl, \CURLINFO_RESPONSE_CODE), $responseHeaders, $result);
    }
}
