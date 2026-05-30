<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Access\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

        // Walk ancestry from the folder upward.
        $current = $folder;
        $isOriginal = true;
        while ($current !== null) {
            $best = $this->matchOnFolder($current, $subjectSet, allowCascadeOnly: ! $isOriginal);
            if ($best !== null) {
                return $best;
            }
            $isOriginal = false;
            $current = $current->parent;
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
     * @param  array<int, Subject>  $subjects
     */
    private function matchOnFolder(MediaLibraryFolder $folder, array $subjects, bool $allowCascadeOnly): ?Capability
    {
        /** @var HasMany<FolderPermission, MediaLibraryFolder> $relation */
        $relation = $folder->permissions();

        $query = $relation->getQuery();
        if ($allowCascadeOnly) {
            $query->where('cascade', true);
        }
        /** @var iterable<int, FolderPermission> $rows */
        $rows = $query->get();

        $best = null;
        foreach ($rows as $row) {
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
