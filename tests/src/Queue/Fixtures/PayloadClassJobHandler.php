<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue\Fixtures;

use Spiral\Queue\HandlerInterface;

final class PayloadClassJobHandler implements HandlerInterface
{
    public function invoke(string $name, string $id, PayloadClass $payload): void
    {
    }

    public function handle(string $name, string $id, array $payload): void
    {
    }
}
