<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Events\FolderMoved;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemRestored;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemTagged;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemTrashed;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemUntagged;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibrarySavedSearch;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Exceptions\SelfReferentialFolder;
use Kurt\Modules\MediaLibrary\Sharing\Events\ShareLinkCreated;
use Kurt\Modules\MediaLibrary\Sharing\Events\ShareLinkRevoked;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Sharing\Support\ShareLinkSigner;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;
use Kurt\Modules\MediaLibrary\Storage\Support\ReplaceCoordinator;
use Kurt\Modules\MediaLibrary\Storage\Support\UploadCoordinator;

/**
 * Top-level facade-service for the media library. Wraps the
 * lower-level coordinators (uploads, replace, sharing) and exposes
 * a stable surface that matches spec §16.
 */
final class MediaLibrary
{
    public function __construct(
        private readonly UploadCoordinator $uploads,
        private readonly ReplaceCoordinator $replaces,
        private readonly ShareLinkSigner $signer,
    ) {}

    // ----------------------------------------------------------------
    // Uploads
    // ----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upload(
        UploadedFile $file,
        ?MediaLibraryOwner $owner = null,
        ?MediaLibraryFolder $folder = null,
        array $attributes = [],
    ): MediaLibraryItem {
        return $this->uploads->upload($file, $owner, $folder, $attributes);
    }

    /**
     * @param  array<string, mixed>  $filenameMeta
     */
    public function initiateUpload(?MediaLibraryOwner $owner, array $filenameMeta): MediaLibraryPendingUpload
    {
        return $this->uploads->initiateUpload($owner, $filenameMeta);
    }

    public function completeUpload(string $uploadId): MediaLibraryItem
    {
        return $this->uploads->completeUpload($uploadId);
    }

    public function cancelUpload(string $uploadId): void
    {
        $this->uploads->cancelUpload($uploadId);
    }

    public function replace(
        MediaLibraryItem $item,
        UploadedFile|MediaLibraryPendingUpload $new,
        string $changelog,
    ): MediaLibraryItem {
        return $this->replaces->replace($item, $new, $changelog);
    }

    // ----------------------------------------------------------------
    // Folders
    // ----------------------------------------------------------------

    public function createFolder(
        MediaLibraryOwner $owner,
        string $name,
        ?MediaLibraryFolder $parent = null,
    ): MediaLibraryFolder {
        $authUserId = auth()->id();
        $authUserId = is_int($authUserId) ? $authUserId : null;

        return MediaLibraryFolder::create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
            'parent_id' => $parent?->id,
            'slug' => Str::slug($name),
            'name' => ['en' => $name],
            'visibility' => Visibility::Private,
            'created_by' => $authUserId,
        ]);
    }

    public function moveFolderTo(
        MediaLibraryFolder $folder,
        ?MediaLibraryFolder $newParent,
    ): MediaLibraryFolder {
        if ($newParent !== null && $newParent->id === $folder->id) {
            throw new SelfReferentialFolder('Cannot move a folder onto itself');
        }

        // Guard against moving a folder into its own descendant (would orphan the subtree).
        if ($newParent !== null && str_starts_with((string) $newParent->path, (string) $folder->path.'/')) {
            throw new SelfReferentialFolder('Cannot move a folder into its own descendant');
        }

        $oldParentId = $folder->parent_id;
        $folder->parent_id = $newParent?->id;
        $folder->save();

        // Recompute this node + entire subtree so descendant paths/depths
        // are never left pointing at the old location. rebuildSubtree() writes
        // the new path/depth back onto $folder in memory, so it is up to date.
        $parentPath = $newParent === null ? '' : $newParent->path;
        $parentDepth = $newParent === null ? -1 : $newParent->depth;
        $this->rebuildSubtree($folder, $parentPath, $parentDepth + 1);

        FolderMoved::dispatch($folder, $oldParentId, $newParent?->id);

        return $folder;
    }

    /**
     * @param  array<int, int>  $itemIds
     */
    public function moveItems(array $itemIds, ?MediaLibraryFolder $newFolder): int
    {
        return MediaLibraryItem::query()
            ->whereIn('id', $itemIds)
            ->update(['folder_id' => $newFolder?->id]);
    }

    // ----------------------------------------------------------------
    // Trash + restore
    // ----------------------------------------------------------------

    public function trash(MediaLibraryItem|MediaLibraryFolder $target): void
    {
        $target->delete();

        if ($target instanceof MediaLibraryItem) {
            ItemTrashed::dispatch($target);
        }
    }

    public function restore(MediaLibraryItem|MediaLibraryFolder $target): void
    {
        $target->restore();

        if ($target instanceof MediaLibraryItem) {
            ItemRestored::dispatch($target);
        }
    }

    // ----------------------------------------------------------------
    // Sharing
    // ----------------------------------------------------------------

    /**
     * @param  array<int, string>  $abilities
     */
    public function shareItem(
        MediaLibraryItem $item,
        int $expiresInSeconds,
        array $abilities = ['view'],
        ?string $invitee = null,
    ): string {
        $authUserId = auth()->id();
        $authUserId = is_int($authUserId) ? $authUserId : null;

        $link = ShareLink::create([
            'item_id' => $item->id,
            'token' => $this->signer->generateToken(),
            'abilities' => $abilities,
            'invitee_email' => $invitee,
            'expires_at' => $expiresInSeconds > 0 ? now()->addSeconds($expiresInSeconds) : null,
            'created_by' => $authUserId,
        ]);

        ShareLinkCreated::dispatch($link);

        return $this->signer->url($link->token);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    public function shareFolder(
        MediaLibraryFolder $folder,
        int $expiresInSeconds,
        array $abilities = ['view'],
        ?string $invitee = null,
    ): string {
        $authUserId = auth()->id();
        $authUserId = is_int($authUserId) ? $authUserId : null;

        $link = ShareLink::create([
            'folder_id' => $folder->id,
            'token' => $this->signer->generateToken(),
            'abilities' => $abilities,
            'invitee_email' => $invitee,
            'expires_at' => $expiresInSeconds > 0 ? now()->addSeconds($expiresInSeconds) : null,
            'created_by' => $authUserId,
        ]);

        ShareLinkCreated::dispatch($link);

        return $this->signer->url($link->token);
    }

    public function revokeShare(string $token): void
    {
        $link = ShareLink::query()->where('token', $token)->first();

        if ($link === null) {
            return;
        }

        $link->forceFill(['revoked_at' => now()])->save();

        ShareLinkRevoked::dispatch($link);
    }

    // ----------------------------------------------------------------
    // Tagging
    // ----------------------------------------------------------------

    public function tag(MediaLibraryItem $item, string|MediaLibraryTag $tag): MediaLibraryTag
    {
        $tagModel = $tag instanceof MediaLibraryTag
            ? $tag
            : MediaLibraryTag::firstOrCreate(
                [
                    'owner_type' => $item->owner_type,
                    'owner_id' => $item->owner_id,
                    'slug' => Str::slug($tag),
                ],
                ['name' => ['en' => $tag]],
            );

        $item->tags()->syncWithoutDetaching([$tagModel->id]);

        ItemTagged::dispatch($item, $tagModel);

        return $tagModel;
    }

    public function untag(MediaLibraryItem $item, string|MediaLibraryTag $tag): void
    {
        $tagModel = $tag instanceof MediaLibraryTag
            ? $tag
            : MediaLibraryTag::query()
                ->where('owner_type', $item->owner_type)
                ->where('owner_id', $item->owner_id)
                ->where('slug', Str::slug($tag))
                ->first();

        if ($tagModel === null) {
            return;
        }

        $item->tags()->detach($tagModel->id);

        ItemUntagged::dispatch($item, $tagModel);
    }

    // ----------------------------------------------------------------
    // Saved searches
    // ----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $filters
     */
    public function saveSearch(Model $user, string $name, array $filters): MediaLibrarySavedSearch
    {
        return MediaLibrarySavedSearch::create([
            'user_id' => $user->getKey(),
            'name' => $name,
            'filters' => $filters,
        ]);
    }

    /**
     * @return Collection<int, MediaLibraryItem>
     */
    public function runSearch(MediaLibrarySavedSearch $search): Collection
    {
        $filters = (array) $search->filters;

        $query = MediaLibraryItem::query();

        if (isset($filters['mime']) && is_string($filters['mime'])) {
            $query->byMimeType($filters['mime']);
        }

        if (isset($filters['tag_id']) && (is_int($filters['tag_id']) || is_string($filters['tag_id']))) {
            $tagId = (int) $filters['tag_id'];
            $query->whereHas('tags', function (Builder $rel) use ($tagId): void {
                $rel->whereKey($tagId);
            });
        }

        if (isset($filters['q']) && is_string($filters['q'])) {
            $query->search($filters['q']);
        }

        if (isset($filters['folder_id']) && (is_int($filters['folder_id']) || is_string($filters['folder_id']))) {
            $query->where('folder_id', (int) $filters['folder_id']);
        }

        return $query->get();
    }

    // ----------------------------------------------------------------
    // Maintenance
    // ----------------------------------------------------------------

    public function pruneVersions(MediaLibraryItem $item, int $keepNewest = 10): int
    {
        $versions = $item->versions()
            ->orderByDesc('created_at')
            ->skip($keepNewest)
            ->take(1000)
            ->get();

        $count = 0;
        foreach ($versions as $version) {
            $version->delete();
            $count++;
        }

        return $count;
    }

    public function pruneVariants(MediaLibraryItem $item, int $unusedDays = 30): int
    {
        $cutoff = now()->subDays($unusedDays);

        return $item->variants()
            ->where(function (Builder $q) use ($cutoff): void {
                $q->where('last_used_at', '<', $cutoff)
                    ->orWhere(function (Builder $w) use ($cutoff): void {
                        $w->whereNull('last_used_at')
                            ->where('generated_at', '<', $cutoff);
                    });
            })
            ->delete();
    }

    public function rebuildPaths(MediaLibraryOwner $owner): int
    {
        $rebuilt = 0;

        $roots = MediaLibraryFolder::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->whereNull('parent_id')
            ->get();

        foreach ($roots as $root) {
            $rebuilt += $this->rebuildSubtree($root, '', 0);
        }

        return $rebuilt;
    }

    public function recountCounters(MediaLibraryOwner $owner): int
    {
        $count = 0;

        $folders = MediaLibraryFolder::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->get();

        foreach ($folders as $folder) {
            $folder->forceFill([
                'item_count' => $folder->items()->count(),
                'descendant_count' => MediaLibraryFolder::query()
                    ->where('path', 'like', $folder->path.'/%')
                    ->count(),
            ])->save();
            $count++;
        }

        return $count;
    }

    private function rebuildSubtree(MediaLibraryFolder $folder, string $parentPath, int $depth): int
    {
        $folder->forceFill([
            'path' => $parentPath.'/'.$folder->slug,
            'depth' => $depth,
        ])->save();

        $count = 1;
        // Re-query children rather than reusing a cached relation so that a
        // freshly-moved subtree is traversed against current parent_id values.
        foreach ($folder->children()->get() as $child) {
            $count += $this->rebuildSubtree($child, $folder->path, $depth + 1);
        }

        return $count;
    }
}
