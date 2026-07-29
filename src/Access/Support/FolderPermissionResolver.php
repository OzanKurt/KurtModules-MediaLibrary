<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Access\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Kurt\Modules\Core\Support\GenerationalModuleCache;
use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Access\Values\Subject;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Throwable;

final class FolderPermissionResolver
{
    /**
     * Generational cache scope. Bumped on FolderPermissionChanged / FolderMoved,
     * which invalidates the entire ACL keyspace at once (see the `cache` config
     * block + the FlushAclCache listener).
     */
    private const CACHE_SCOPE = 'acl';

    public function __construct(
        private readonly MediaSubjectResolver $subjects,
        private readonly ?GenerationalModuleCache $cache = null,
    ) {}

    /**
     * Highest capability the user holds on the folder (null = none).
     *
     * L1 (per-request memo) lives in {@see MediaLibraryAccess}. This is the L2
     * cross-request layer: the live ancestor-chain walk is wrapped in a
     * generational cache keyed by subject + a role fingerprint + folder. Role
     * changes have no domain event, so they ride IN the key (rolesHash) and
     * self-invalidate; permission/move changes bump the whole scope.
     *
     * Fail CLOSED: with no cache, caching disabled, or ANY cache error the
     * capability is resolved live — never granted from a stale or errored read.
     */
    public function highestCapability(?Authenticatable $user, MediaLibraryFolder $folder): ?Capability
    {
        $subjectSet = $this->subjects->subjects($user);

        if ($this->cache === null) {
            return $this->resolve($user, $folder, $subjectSet);
        }

        $subjectId = $user?->getAuthIdentifier();
        $subjectId = $subjectId === null ? 'guest' : (string) $subjectId;
        $key = sprintf(
            'subject:%s:roles:%s:folder:%s',
            $subjectId,
            $this->rolesHash($user, $subjectSet),
            (string) $folder->getKey(),
        );

        try {
            $cached = $this->cache->remember(
                self::CACHE_SCOPE,
                $key,
                fn (): ?Capability => $this->resolve($user, $folder, $subjectSet),
            );
        } catch (Throwable) {
            // Never fail open to a granted capability: fall back to live resolution.
            return $this->resolve($user, $folder, $subjectSet);
        }

        // Only a genuine Capability result is honoured; anything else denies.
        return $cached instanceof Capability ? $cached : null;
    }

    /**
     * Deterministic fingerprint of the subject's roles. Anonymous → 'none'.
     * Roles come from the resolver's `role` subjects (a custom resolver may emit
     * them); sorting keeps the hash stable regardless of order. Any input beyond
     * subjectId/folderId that affects the result MUST fold in here, or a change
     * with no bump signal would serve stale.
     *
     * @param  array<int, Subject>  $subjectSet
     */
    private function rolesHash(?Authenticatable $user, array $subjectSet): string
    {
        if ($user === null) {
            return 'none';
        }

        $roles = [];
        foreach ($subjectSet as $subject) {
            if ($subject->type === SubjectType::Role && $subject->value !== null) {
                $roles[] = $subject->value;
            }
        }

        sort($roles);

        return substr(sha1(implode(',', $roles)), 0, 12);
    }

    /**
     * Live capability resolution (the expensive path L2 caches).
     *
     * @param  array<int, Subject>  $subjectSet
     */
    private function resolve(?Authenticatable $user, MediaLibraryFolder $folder, array $subjectSet): ?Capability
    {
        // Resolve the folder + all of its ancestors (plus their permission rows)
        // in a constant number of queries. The previous lazy `->parent` walk cost
        // depth×2 queries (one per ancestor + one per permission set); this loads
        // the whole chain via a single whereIn on the materialised `path` segments
        // and eager-loads permissions, so ACL checks are O(1) regardless of depth.
        $chain = $this->ancestryChain($folder);

        $isOriginal = true;
        foreach ($chain as $current) {
            $best = $this->matchOnFolder($current, $subjectSet, allowCascadeOnly: ! $isOriginal);
            if ($best !== null) {
                return $best;
            }
            $isOriginal = false;
        }

        // Visibility fallback on the original folder.
        return match ($folder->visibility) {
            Visibility::Public => Capability::Download,
            Visibility::Restricted => null,
            Visibility::Private => $user !== null && $folder->owner_id === $user->getAuthIdentifier()
                ? Capability::Manage
                : null,
        };
    }

    /**
     * Return the folder and its ancestors ordered from the folder itself up to
     * the root, each with its `permissions` relation eager-loaded.
     *
     * @return array<int, MediaLibraryFolder>
     */
    private function ancestryChain(MediaLibraryFolder $folder): array
    {
        // The `path` column stores '/slug/slug/...'; every ancestor is a strict
        // prefix of it. Rebuild each prefix so we can fetch the whole line in one
        // query. Scoped by owner because `path` is only unique within an owner.
        $segments = array_values(array_filter(
            explode('/', $folder->path),
            static fn (string $segment): bool => $segment !== '',
        ));

        $paths = [];
        $accumulator = '';
        foreach ($segments as $segment) {
            $accumulator .= '/'.$segment;
            $paths[] = $accumulator;
        }

        if ($paths === []) {
            return [$folder->loadMissing('permissions')];
        }

        $loaded = MediaLibraryFolder::query()
            ->where('owner_type', $folder->owner_type)
            ->where('owner_id', $folder->owner_id)
            ->whereIn('path', $paths)
            ->with('permissions')
            ->get()
            ->keyBy('path');

        $chain = [];
        foreach (array_reverse($paths) as $path) {
            $node = $loaded->get($path);
            if ($node instanceof MediaLibraryFolder) {
                $chain[] = $node;
            }
        }

        if ($chain === []) {
            return [$folder->loadMissing('permissions')];
        }

        return $chain;
    }

    /**
     * @param  array<int, Subject>  $subjects
     */
    private function matchOnFolder(MediaLibraryFolder $folder, array $subjects, bool $allowCascadeOnly): ?Capability
    {
        /** @var Collection<int, FolderPermission> $rows */
        $rows = $folder->permissions;

        $best = null;
        foreach ($rows as $row) {
            if ($allowCascadeOnly && ! $row->cascade) {
                continue;
            }

            foreach ($subjects as $subject) {
                if ($subject->matches($row->subject_type->value, $row->subject_value)) {
                    if ($best === null || $row->capability->rank() > $best->rank()) {
                        $best = $row->capability;
                    }
                }
            }
        }

        return $best;
    }
}
