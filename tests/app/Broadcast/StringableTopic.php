<?php

declare(strict_types=1);

namespace Spiral\App\Broadcast;

final class StringableTopic implements \Stringable
{
    public function __construct(
        private readonly string $name
    ) {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
