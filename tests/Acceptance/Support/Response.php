<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\Support;

final class Response
{
    /**
     * @param array<lowercase-string, list<string>> $headers Headers and trailers.
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[\strtolower($name)][0] ?? null;
    }
}
