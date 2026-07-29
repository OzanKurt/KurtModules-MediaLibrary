<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Access\Listeners;

use Kurt\Modules\Core\Support\ModuleCacheFactory;

/**
 * Invalidates the cross-request ACL cache when a folder's permissions change
 * (FolderPermissionChanged) or a folder moves (FolderMoved) — the two ways a
 * cached capability can go stale that have no representation in the cache key.
 *
 * A single bump() drops the `acl` scope's generation token, orphaning every
 * cached capability at once (global invalidation, the accepted tradeoff). This
 * is the security-critical wiring: without it a revoked or relocated grant
 * could keep serving stale access. Registered for both events in the provider.
 */
final class FlushAclCache
{
    public function __construct(private readonly ModuleCacheFactory $cache) {}

    public function handle(object $event): void
    {
        $this->cache->generationalFor('media-library')->bump('acl');
    }
}
