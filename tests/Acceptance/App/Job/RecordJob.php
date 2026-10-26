<?php

declare(strict_types=1);

namespace Spiral\Tests\Acceptance\App\Job;

use Spiral\Boot\DirectoriesInterface;
use Spiral\Queue\JobHandler;

/**
 * Leaves a file in the runtime directory, so the test sees from outside that the job was consumed.
 */
final class RecordJob extends JobHandler
{
    public function invoke(DirectoriesInterface $dirs, string $name, string $value): void
    {
        $dir = $dirs->get('runtime') . 'jobs';
        \is_dir($dir) or \mkdir($dir, 0777, true);
        \file_put_contents("$dir/$name", $value);
    }
}
