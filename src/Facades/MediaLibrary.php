<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Facade;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibrarySavedSearch;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary as Service;

/**
 * @method static MediaLibraryItem upload(UploadedFile $file, ?MediaLibraryOwner $owner = null, ?MediaLibraryFolder $folder = null, array<string, mixed> $attributes = [])
 * @method static MediaLibraryPendingUpload initiateUpload(?MediaLibraryOwner $owner, array<string, mixed> $filenameMeta)
 * @method static MediaLibraryItem completeUpload(string $uploadId)
 * @method static void cancelUpload(string $uploadId)
 * @method static MediaLibraryItem replace(MediaLibraryItem $item, UploadedFile|MediaLibraryPendingUpload $new, string $changelog)
 * @method static MediaLibraryFolder createFolder(MediaLibraryOwner $owner, string $name, ?MediaLibraryFolder $parent = null)
 * @method static MediaLibraryFolder moveFolderTo(MediaLibraryFolder $folder, ?MediaLibraryFolder $newParent)
 * @method static int moveItems(array<int, int> $itemIds, ?MediaLibraryFolder $newFolder)
 * @method static void trash(MediaLibraryItem|MediaLibraryFolder $target)
 * @method static void restore(MediaLibraryItem|MediaLibraryFolder $target)
 * @method static string shareItem(MediaLibraryItem $item, int $expiresInSeconds, array<int, string> $abilities = ['view'], ?string $invitee = null)
 * @method static string shareFolder(MediaLibraryFolder $folder, int $expiresInSeconds, array<int, string> $abilities = ['view'], ?string $invitee = null)
 * @method static void revokeShare(string $token)
 * @method static MediaLibraryTag tag(MediaLibraryItem $item, string|MediaLibraryTag $tag)
 * @method static void untag(MediaLibraryItem $item, string|MediaLibraryTag $tag)
 * @method static MediaLibrarySavedSearch saveSearch(Model $user, string $name, array<string, mixed> $filters)
 * @method static Collection<int, MediaLibraryItem> runSearch(MediaLibrarySavedSearch $search)
 * @method static int pruneVersions(MediaLibraryItem $item, int $keepNewest = 10)
 * @method static int pruneVariants(MediaLibraryItem $item, int $unusedDays = 30)
 * @method static int rebuildPaths(MediaLibraryOwner $owner)
 * @method static int recountCounters(MediaLibraryOwner $owner)
 *
 * @see Service
 */
final class MediaLibrary extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Service::class;
    }
}
