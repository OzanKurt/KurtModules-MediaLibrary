<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Access\Support\FolderPermissionResolver;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

/**
 * @return array{0: MediaLibraryFolder, 1: MediaLibraryFolder}
 */
function buildFolderChain(int $depth, int $ownerId): array
{
    $root = MediaLibraryFolder::factory()->create([
        'owner_type' => 'stub_owner',
        'owner_id' => $ownerId,
        'parent_id' => null,
        'visibility' => Visibility::Private,
    ]);

    $current = $root;
    for ($i = 0; $i < $depth; $i++) {
        $current = MediaLibraryFolder::factory()->create([
            'owner_type' => 'stub_owner',
            'owner_id' => $ownerId,
            'parent_id' => $current->id,
            'visibility' => Visibility::Private,
        ]);
    }

    return [$root, $current];
}

it('resolves folder ACL in a constant number of queries regardless of depth', function (): void {
    $resolver = new FolderPermissionResolver(new DefaultSubjectResolver);

    [, $shallow] = buildFolderChain(3, 601);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $resolver->highestCapability(null, $shallow);
    $shallowCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    [, $deep] = buildFolderChain(9, 602);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $resolver->highestCapability(null, $deep);
    $deepCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // The old lazy parent walk was depth×2 queries; the new resolver loads the
    // whole chain + permissions in a fixed handful of queries, so a 9-deep tree
    // costs the same as a 3-deep one.
    expect($deepCount)->toBe($shallowCount);
    expect($deepCount)->toBeLessThanOrEqual(3);
});

it('honours a cascading permission on an ancestor but ignores a non-cascading one', function (): void {
    $resolver = new FolderPermissionResolver(new DefaultSubjectResolver);

    [$root, $leaf] = buildFolderChain(2, 603);

    // Non-cascading Everyone permission on the root does NOT reach the leaf.
    $permission = FolderPermission::factory()->create([
        'folder_id' => $root->id,
        'subject_type' => SubjectType::Everyone,
        'subject_value' => null,
        'capability' => Capability::Download,
        'cascade' => false,
    ]);

    expect($resolver->highestCapability(null, $leaf))->toBeNull();

    // Flip it to cascade and it now grants Download on the leaf.
    $permission->forceFill(['cascade' => true])->save();

    expect($resolver->highestCapability(null, $leaf))->toBe(Capability::Download);
});
