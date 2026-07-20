<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Access\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Access\Values\Subject;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

final class FolderPermissionResolver
{
    public function __construct(private readonly MediaSubjectResolver $subjects) {}

    public function highestCapability(?Authenticatable $user, MediaLibraryFolder $folder): ?Capability
    {
        $subjectSet = $this->subjects->subjects($user);

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
