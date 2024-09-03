<?php

declare(strict_types=1);

namespace Spiral\App\Job;

use Psr\Container\ContainerInterface;
use Spiral\Core\Internal\Introspector;
use Spiral\Queue\JobHandler;
use Spiral\Queue\TaskInterface;

final class ScopedJob extends JobHandler
{
    public static array $scopes = [];
    public static TaskInterface $task;

    public function invoke(TaskInterface $task, ContainerInterface $container): void
    {
        self::$task = $task;
        self::$scopes = Introspector::scopeNames($container);
    }
}
