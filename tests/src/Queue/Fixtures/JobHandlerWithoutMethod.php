<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue\Fixtures;

use Spiral\Queue\HandlerInterface;

final class JobHandlerWithoutMethod implements HandlerInterface
{
    public function handle(string $name, string $id, array $payload): void
    {
    }
}
