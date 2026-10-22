<?php

declare(strict_types=1);

use Spiral\Testing\SpiralTestingPlugin;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    src: ['src'],
    suites: [
        new SuiteConfig(
            name: 'Unit',
            location: ['tests/src'],
        ),
    ],
    plugins: [new SpiralTestingPlugin()],
);
