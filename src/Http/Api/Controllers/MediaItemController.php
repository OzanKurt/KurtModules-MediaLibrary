<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Http\Api\Controllers;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Kurt\Modules\Core\Http\Concerns\HandlesApiQuery;
use Kurt\Modules\MediaLibrary\Catalog\Events\ItemDownloaded;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Http\Api\Resources\MediaLibraryItemResource;
use Kurt\Modules\MediaLibrary\Sharing\Enums\AccessAction;
use Kurt\Modules\MediaLibrary\Sharing\Support\AccessLogger;
use Kurt\Modules\MediaLibrary\Storage\Models\MediaLibraryPendingUpload;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class MediaItemController extends MediaApiController
{
    use HandlesApiQuery;

    public function __construct(
        private readonly MediaLibrary $library,
        private readonly AccessLogger $accessLogger,
    ) {}

    /**
     * List items, ACL-scoped. With `?folder={id}` it lists that folder's items
     * (requires view on the folder — an item in a folder the caller cannot see
     * is never leaked); without it, the current owner's unfiled items. Each
     * candidate is still filtered through the item view Policy.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MediaLibraryItem::query();

        $folderId = $request->query('folder');
        if (is_scalar($folderId) && $folderId !== '') {
            /** @var MediaLibraryFolder $folder */
            $folder = MediaLibraryFolder::query()->findOrFail($folderId);
            $this->authorize('view', $folder);

            $query->where('folder_id', $folder->getKey());
        } else {
            $owner = $this->resolveOwner($request);

            if ($owner === null) {
                return $this->respondPaginated(
                    $this->paginateCollection(new EloquentCollection, $request),
                    MediaLibraryItemResource::class,
                );
            }

            $query->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey())
                ->whereNull('folder_id');
        }

        $query = $this->applyApiFilters($query, $request, ['mime_type' => 'like', 'filename' => 'like', 'folder_id' => 'exact']);
        $query = $this->applyApiSorts($query, $request, ['created_at', 'byte_size', 'filename', 'id']);

        /** @var EloquentCollection<int, MediaLibraryItem> $items */
        $items = $query->orderBy('id')->get();

        $visible = $this->filterAuthorised($items, 'view');

        return $this->respondPaginated(
            $this->paginateCollection($visible, $request),
            MediaLibraryItemResource::class,
        );
    }

    public function show(MediaLibraryItem $item): JsonResponse
    {
        $this->authorize('view', $item);

        return $this->respond(MediaLibraryItemResource::make($item));
    }

    /**
     * Server-proxy upload: the file is POSTed to the API, which streams it
     * through the UploadCoordinator so hashing, the extractor pipeline, and
     * GDPR bookkeeping all run. See the README for the presigned direct-to-S3
     * flow (initiateUpload/completeUpload) when you want to skip the proxy.
     */
    public function store(Request $request): JsonResponse
    {
        $owner = $this->resolveOwner($request);

        if ($owner === null) {
            return $this->fail('The authenticated user cannot own media.', 422);
        }

        $data = $this->validate($request, [
            'file' => ['required', 'file'],
            'folder_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string'],
            'caption' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $folder = null;
        if (isset($data['folder_id'])) {
            /** @var MediaLibraryFolder $folder */
            $folder = MediaLibraryFolder::query()->findOrFail($data['folder_id']);
            // Uploading into a folder requires management rights on it.
            $this->authorize('manage', $folder);
        }

        $attributes = array_filter([
            'title' => isset($data['title']) ? ['en' => (string) $data['title']] : null,
            'alt_text' => $data['alt_text'] ?? null,
            'caption' => $data['caption'] ?? null,
            'description' => $data['description'] ?? null,
        ], static fn ($value): bool => $value !== null);

        $file = $request->file('file');
        $item = $this->library->upload($file, $owner, $folder, $attributes);

        return $this->respondCreated(MediaLibraryItemResource::make($item));
    }

    public function update(Request $request, MediaLibraryItem $item): JsonResponse
    {
        $this->authorize('manage', $item);

        $data = $this->validate($request, [
            'title' => ['sometimes', 'string', 'max:255'],
            'alt_text' => ['sometimes', 'nullable', 'string'],
            'caption' => ['sometimes', 'nullable', 'string'],
            'description' => ['sometimes', 'nullable', 'string'],
            'folder_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        // Moving is a counter-aware domain operation; run it through the service.
        if (array_key_exists('folder_id', $data)) {
            $target = null;
            if ($data['folder_id'] !== null) {
                /** @var MediaLibraryFolder $target */
                $target = MediaLibraryFolder::query()->findOrFail($data['folder_id']);
                $this->authorize('manage', $target);
            }

            $this->library->moveItems([(int) $item->getKey()], $target);
        }

        $dirty = [];
        if (isset($data['title'])) {
            $dirty['title'] = ['en' => (string) $data['title']];
        }
        foreach (['alt_text', 'caption', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $dirty[$field] = $data[$field];
            }
        }
        if ($dirty !== []) {
            $item->forceFill($dirty)->save();
        }

        return $this->respond(MediaLibraryItemResource::make($item->refresh()));
    }

    public function destroy(MediaLibraryItem $item): JsonResponse
    {
        $this->authorize('manage', $item);

        $this->library->trash($item);

        return $this->respondNoContent();
    }

    /**
     * Replace an item's underlying file (stable id) through the
     * ReplaceCoordinator, capturing a version and re-running extraction.
     */
    public function replace(Request $request, MediaLibraryItem $item): JsonResponse
    {
        $this->authorize('manage', $item);

        $data = $this->validate($request, [
            'file' => ['required', 'file'],
            'changelog' => ['nullable', 'string', 'max:1000'],
        ]);

        $replaced = $this->library->replace(
            $item,
            $request->file('file'),
            (string) ($data['changelog'] ?? ''),
        );

        return $this->respond(MediaLibraryItemResource::make($replaced));
    }

    /**
     * Stream an item's bytes. Two accepted credentials: a valid signature (from
     * the signed-url endpoint) OR download rights under the folder ACL. Mirrors
     * the share-link controller's local/remote-disk handling.
     */
    public function download(Request $request, MediaLibraryItem $item): Response
    {
        if (! $request->hasValidSignature()) {
            $this->authorize('download', $item);
        }

        $media = $item->spatieMedia();
        if ($media === null) {
            abort(410, 'media_gone');
        }

        $this->accessLogger->log($item, null, $request->user(), AccessAction::Download);
        $item->increment('download_count');
        ItemDownloaded::dispatch($item, $request->user());

        $diskName = (string) $media->disk;

        if (config("filesystems.disks.{$diskName}.driver") === 'local') {
            return response()->download(
                $media->getPath(),
                $media->file_name,
                [],
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            );
        }

        return Storage::disk($diskName)->download($media->getPathRelativeToRoot(), $media->file_name);
    }

    /**
     * Issue a time-limited signed URL to {@see download()} so a client (or a
     * browser <img>/<a>) can fetch the bytes without carrying an auth token.
     * The caller must hold download rights to mint the link.
     */
    public function signedUrl(Request $request, MediaLibraryItem $item): JsonResponse
    {
        $this->authorize('download', $item);

        $ttl = $request->query('expires_in');
        $ttl = is_numeric($ttl) ? (int) $ttl : 900;
        $ttl = max(60, min($ttl, 86_400));

        $url = URL::temporarySignedRoute(
            'media-library.api.items.download',
            now()->addSeconds($ttl),
            ['item' => $item->getKey()],
        );

        return $this->respond([
            'url' => $url,
            'expires_in' => $ttl,
        ]);
    }

    /**
     * Initiate a presigned direct-to-storage upload. Returns the pending upload
     * with its presigned PUT URL + headers + object key. The client PUTs the
     * bytes straight to storage, then calls {@see completeUpload()}.
     */
    public function initiateUpload(Request $request): JsonResponse
    {
        $owner = $this->resolveOwner($request);

        if ($owner === null) {
            return $this->fail('The authenticated user cannot own media.', 422);
        }

        $data = $this->validate($request, [
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:255'],
            'byte_size' => ['nullable', 'integer', 'min:0'],
        ]);

        $pending = $this->library->initiateUpload($owner, $data);

        return $this->respondCreated([
            'upload_id' => $pending->upload_id,
            'url' => $pending->driver_payload['url'] ?? null,
            'headers' => $pending->driver_payload['headers'] ?? [],
            'key' => $pending->driver_payload['key'] ?? null,
            'expires_at' => $pending->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Finalize a presigned upload once the client has PUT the object. Runs the
     * same coordinator path (validation, extraction, item persistence).
     */
    public function completeUpload(Request $request, string $uploadId): JsonResponse
    {
        // Authorise the caller against the pending row they own before touching
        // the coordinator, so one user cannot finalize another's upload.
        $pending = MediaLibraryPendingUpload::query()->where('upload_id', $uploadId)->firstOrFail();
        $owner = $this->resolveOwner($request);

        if ($owner === null
            || $pending->owner_type !== $owner->getMorphClass()
            || (string) $pending->owner_id !== (string) $owner->getKey()) {
            abort(403);
        }

        $item = $this->library->completeUpload($uploadId);

        return $this->respondCreated(MediaLibraryItemResource::make($item));
    }

    public function cancelUpload(Request $request, string $uploadId): JsonResponse
    {
        $pending = MediaLibraryPendingUpload::query()->where('upload_id', $uploadId)->firstOrFail();
        $owner = $this->resolveOwner($request);

        if ($owner === null
            || $pending->owner_type !== $owner->getMorphClass()
            || (string) $pending->owner_id !== (string) $owner->getKey()) {
            abort(403);
        }

        $this->library->cancelUpload($uploadId);

        return $this->respondNoContent();
    }
}
