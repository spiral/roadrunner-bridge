<?php

declare(strict_types=1);

namespace Spiral\App\Job;

use Spiral\Queue\HandlerInterface;

final class TestJob implements HandlerInterface
{
    public function handle(string $name, string $id, array $payload): void
    {
    }
}
