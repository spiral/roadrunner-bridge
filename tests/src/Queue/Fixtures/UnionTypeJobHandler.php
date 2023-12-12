<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue\Fixtures;

use Spiral\Queue\HandlerInterface;

final class UnionTypeJobHandler implements HandlerInterface
{
    public function invoke(string $name, string $id, array|PayloadClass|null $payload): void
    {
    }

    public function handle(string $name, string $id, array $payload): void
    {
    }
}
