<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue\Fixtures;

final class PayloadClass
{
    public function __construct(
        public readonly string $url,
    ) {
    }
}
