<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryAttachment;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibrarySavedSearch;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryStorage;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryTag;
use Kurt\Modules\MediaLibrary\Sharing\Models\AccessLogEntry;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVariant;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryVersion;

/**
 * Bounded GDPR subject purge: hard-deletes everything the media library holds
 * for a single owner (identified by its morph type + id) - items and their
 * stored files, folders, versions, variants, attachments, share links, tags,
 * saved searches, pending uploads, and the access-log rows tied to those items.
 * Optionally anonymises the access-log entries that recorded the subject as a
 * viewer. This is deliberately a single command, not an erasure framework.
 */
final class PurgeSubjectCommand extends Command
{
    /** @var string */
    protected $signature = 'media-library:purge-subject
        {type : The owner morph type (class name or morph alias, as stored in owner_type)}
        {id : The owner id}
        {--anonymize-log : Also null out user_id on access-log rows that recorded this subject as a viewer}';

    /** @var string */
    protected $description = 'GDPR: hard-delete (and optionally anonymize) all media library data owned by a subject.';

    public function handle(): int
    {
        // Arguments arrive as strings from the CLI but may be passed as raw
        // scalars via Artisan::call(); normalise either shape to a string.
        $typeArg = $this->argument('type');
        $idArg = $this->argument('id');
        $type = is_scalar($typeArg) ? (string) $typeArg : '';
        $id = is_scalar($idArg) ? (string) $idArg : '';
        $anonymize = (bool) $this->option('anonymize-log');

        /** @var array<string, int> $counts */
        $counts = DB::transaction(function () use ($type, $id, $anonymize): array {
            $items = MediaLibraryItem::withTrashed()
                ->where('owner_type', $type)
                ->where('owner_id', $id)
                ->get();

            /** @var array<int, int> $itemIds */
            $itemIds = $items->pluck('id')->all();
            /** @var array<int, int> $storageIds */
            $storageIds = $items->pluck('storage_id')->filter()->unique()->values()->all();

            $counts = [
                'access_log' => 0,
                'share_links' => 0,
                'versions' => 0,
                'variants' => 0,
                'attachments' => 0,
                'items' => 0,
                'storage' => 0,
                'folders' => 0,
                'tags' => 0,
                'saved_searches' => 0,
                'pending_uploads' => 0,
                'access_log_anonymized' => 0,
            ];

            if ($itemIds !== []) {
                $counts['access_log'] = AccessLogEntry::query()->whereIn('item_id', $itemIds)->delete();
                $counts['share_links'] = ShareLink::withTrashed()->whereIn('item_id', $itemIds)->forceDelete();
                $counts['versions'] = MediaLibraryVersion::query()->whereIn('item_id', $itemIds)->delete();
                $counts['variants'] = MediaLibraryVariant::query()->whereIn('item_id', $itemIds)->delete();
                $counts['attachments'] = MediaLibraryAttachment::query()->whereIn('item_id', $itemIds)->delete();
                DB::table('media_library_item_tag')->whereIn('item_id', $itemIds)->delete();
            }

            foreach ($items as $item) {
                $item->forceDelete();
                $counts['items']++;
            }

            // Deleting the storage host removes the underlying spatie media files.
            foreach (MediaLibraryStorage::query()->whereIn('id', $storageIds)->get() as $storage) {
                $storage->delete();
                $counts['storage']++;
            }

            // Folders: delete deepest-first so parent_id restrictOnDelete never trips.
            $folders = MediaLibraryFolder::withTrashed()
                ->where('owner_type', $type)
                ->where('owner_id', $id)
                ->orderByDesc('depth')
                ->get();

            foreach ($folders as $folder) {
                $folder->forceDelete();
                $counts['folders']++;
            }

            $counts['tags'] = MediaLibraryTag::query()
                ->where('owner_type', $type)
                ->where('owner_id', $id)
                ->delete();

            $counts['pending_uploads'] = MediaLibraryPendingUpload::query()
                ->where('owner_type', $type)
                ->where('owner_id', $id)
                ->delete();

            // Saved searches are keyed by user id rather than a morph owner.
            $counts['saved_searches'] = MediaLibrarySavedSearch::query()
                ->where('user_id', $id)
                ->delete();

            if ($anonymize) {
                $counts['access_log_anonymized'] = AccessLogEntry::query()
                    ->where('user_id', $id)
                    ->update(['user_id' => null]);
            }

            return $counts;
        });

        $this->info(sprintf('Purged media library data for subject [%s:%s].', $type, $id));
        foreach ($counts as $label => $count) {
            $this->line(sprintf('  %-22s %d', $label, $count));
        }

        return self::SUCCESS;
    }
}
