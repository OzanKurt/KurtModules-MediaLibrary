<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Storage\Support\MetadataPipeline;

/**
 * Runs the configured async extractor pipeline (EXIF/GPS, OCR, AI tags, search
 * indexing) over a freshly stored / replaced media item.
 *
 * Dispatched by the upload + replace coordinators after the item is committed.
 * Serializes only the item id so a re-queued job always operates on the current
 * row. Use {@see self::dispatchFor()} rather than dispatching directly so the
 * configured sync-vs-queued mode and queue/connection are honoured.
 */
final class ExtractMediaMetadata implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $itemId) {}

    /**
     * Dispatch the pipeline for an item, honouring
     * `media-library.extractors.dispatch` (queued|sync) and the optional
     * queue connection / name.
     */
    public static function dispatchFor(MediaLibraryItem $item): void
    {
        $mode = (string) config('media-library.extractors.dispatch', 'queued');

        if ($mode === 'sync') {
            self::dispatchSync($item->id);

            return;
        }

        $connection = config('media-library.extractors.connection');
        $queue = config('media-library.extractors.queue');

        self::dispatch($item->id)
            ->onConnection(is_string($connection) && $connection !== '' ? $connection : null)
            ->onQueue(is_string($queue) && $queue !== '' ? $queue : null);
    }

    public function handle(MetadataPipeline $pipeline): void
    {
        $item = MediaLibraryItem::query()->find($this->itemId);

        if ($item === null) {
            return;
        }

        $pipeline->run($item);
    }
}
