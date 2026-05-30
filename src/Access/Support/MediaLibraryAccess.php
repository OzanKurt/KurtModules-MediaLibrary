<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Access\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;

final class MediaLibraryAccess
{
    /** @var array<string, ?Capability> */
    private array $cache = [];

    public function __construct(private readonly FolderPermissionResolver $resolver) {}

    public function check(?Authenticatable $user, MediaLibraryFolder|MediaLibraryItem $target, Capability $needed): bool
    {
        if ($target instanceof MediaLibraryItem) {
            $folder = $target->folder;

            if ($folder === null) {
                // Orphan item: owner-only Manage capability.
                if ($user !== null) {
                    return $target->owner_id === $user->getAuthIdentifier();
                }

                return false;
            }
        } else {
            $folder = $target;
        }

        $key = sprintf('%s:%d', $user?->getAuthIdentifier() ?? 'guest', $folder->getKey());

        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $this->resolver->highestCapability($user, $folder);
        }

        $best = $this->cache[$key];

        return $best !== null && $best->rank() >= $needed->rank();
    }

    public function flush(): void
    {
        $this->cache = [];
    }
}
