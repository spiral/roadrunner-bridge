<?php

declare(strict_types=1);

namespace Spiral\Tests\Queue\Fixtures;

use RoadRunner\Lock\LockInterface;

/**
 * Exclusive locks kept in memory; a held lock fails at once instead of waiting.
 */
final class MemoryLock implements LockInterface
{
    /** @var array<string, string> resource => lock id */
    public array $locks = [];

    /** @var list<string> */
    public array $log = [];

    private int $ids = 0;

    #[\Override]
    public function lock(
        string $resource,
        ?string $id = null,
        int|float|\DateInterval $ttl = 0,
        int|float|\DateInterval $waitTTL = 0,
    ): false|string {
        \assert(\is_int($ttl) && \is_int($waitTTL));
        $this->log[] = "lock $resource ttl=$ttl wait=$waitTTL";
        if (isset($this->locks[$resource])) {
            return false;
        }

        return $this->locks[$resource] = $id ?? 'id-' . ++$this->ids;
    }

    #[\Override]
    public function lockRead(
        string $resource,
        ?string $id = null,
        int|float|\DateInterval $ttl = 0,
        int|float|\DateInterval $waitTTL = 0,
    ): false|string {
        return $this->lock($resource, $id, $ttl, $waitTTL);
    }

    #[\Override]
    public function release(string $resource, string $id): bool
    {
        $this->log[] = "release $resource";
        if (($this->locks[$resource] ?? null) !== $id) {
            return false;
        }

        unset($this->locks[$resource]);
        return true;
    }

    #[\Override]
    public function forceRelease(string $resource): bool
    {
        $this->log[] = "forceRelease $resource";
        $held = isset($this->locks[$resource]);
        unset($this->locks[$resource]);

        return $held;
    }

    #[\Override]
    public function exists(string $resource, ?string $id = null): bool
    {
        return isset($this->locks[$resource]) && ($id === null || $this->locks[$resource] === $id);
    }

    #[\Override]
    public function updateTTL(string $resource, string $id, int|float|\DateInterval $ttl): bool
    {
        return $this->exists($resource, $id);
    }
}
