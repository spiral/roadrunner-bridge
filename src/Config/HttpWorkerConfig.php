<?php

declare(strict_types=1);

namespace Spiral\RoadRunnerBridge\Config;

use Spiral\Core\InjectableConfig;

/**
 * Settings of the RoadRunner HTTP worker loop, `app/config/http-worker.php`.
 *
 * @api
 */
final class HttpWorkerConfig extends InjectableConfig
{
    public const CONFIG = 'http-worker';

    protected array $config = [
        'populateServer' => false,
    ];

    /**
     * Whether every incoming request overwrites the `$_SERVER` superglobal.
     * Off by default: request data is read from the PSR-7 request, and a long-living worker
     * should not leak one request's server params into the next.
     */
    public function shouldPopulateServer(): bool
    {
        return (bool) ($this->config['populateServer'] ?? false);
    }
}
