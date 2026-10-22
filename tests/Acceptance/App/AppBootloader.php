<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\App;

use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Boot\Bootloader\Bootloader;

final class AppBootloader extends Bootloader
{
    public function defineSingletons(): array
    {
        return [
            RequestHandlerInterface::class => Endpoints::class,
        ];
    }
}
