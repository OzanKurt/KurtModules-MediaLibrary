<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Http\Api\Controllers;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kurt\Modules\Core\Http\Concerns\HandlesApiQuery;
use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Models\FolderPermission;
use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Http\Api\Resources\MediaLibraryFolderResource;
use Kurt\Modules\MediaLibrary\Support\MediaLibrary;

final class FolderController extends MediaApiController
{
    use HandlesApiQuery;

    public function __construct(private readonly MediaLibrary $library) {}

    /**
     * List folders, ACL-scoped. With `?parent={id}` it lists that folder's
     * direct children (requires view on the parent); without it, the current
     * owner's root folders. Every candidate is filtered through the folder view
     * Policy so a child behind an ACL the caller lacks is never returned.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MediaLibraryFolder::query();

        $parentId = $request->query('parent');
        if (is_scalar($parentId) && $parentId !== '') {
            /** @var MediaLibraryFolder $parent */
            $parent = MediaLibraryFolder::query()->findOrFail($parentId);
            $this->authorize('view', $parent);

            $query->where('parent_id', $parent->getKey());
        } else {
            $owner = $this->resolveOwner($request);

            if ($owner === null) {
                // A guest (or a user that cannot own media) has no personal root
                // listing; scoped child listings still work via `?parent=`.
                return $this->respondPaginated(
                    $this->paginateCollection(new EloquentCollection, $request),
                    MediaLibraryFolderResource::class,
                );
            }

            $query->where('owner_type', $owner->getMorphClass())
                ->where('owner_id', $owner->getKey())
                ->whereNull('parent_id');
        }

        $query = $this->applyApiFilters($query, $request, ['visibility' => 'exact', 'slug' => 'like']);
        $query = $this->applyApiSorts($query, $request, ['created_at', 'position', 'item_count', 'depth', 'id']);

        /** @var EloquentCollection<int, MediaLibraryFolder> $folders */
        $folders = $query->orderBy('position')->orderBy('id')->get();

        $visible = $this->filterAuthorised($folders, 'view');

        return $this->respondPaginated(
            $this->paginateCollection($visible, $request),
            MediaLibraryFolderResource::class,
        );
    }

    public function show(MediaLibraryFolder $folder): JsonResponse
    {
        $this->authorize('view', $folder);

        return $this->respond(MediaLibraryFolderResource::make($folder));
    }

    public function store(Request $request): JsonResponse
    {
        $owner = $this->resolveOwner($request);

        if ($owner === null) {
            return $this->fail('The authenticated user cannot own media.', 422);
        }

        $data = $this->validate($request, [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'visibility' => ['nullable', Rule::enum(Visibility::class)],
        ]);

        $parent = null;
        if (isset($data['parent_id'])) {
            /** @var MediaLibraryFolder $parent */
            $parent = MediaLibraryFolder::query()->findOrFail($data['parent_id']);
            // Writing a child requires management rights on the parent.
            $this->authorize('manage', $parent);
        }

        $folder = $this->library->createFolder($owner, (string) $data['name'], $parent);

        $dirty = [];
        if (array_key_exists('description', $data)) {
            $dirty['description'] = $data['description'];
        }
        if (isset($data['visibility'])) {
            $dirty['visibility'] = Visibility::from((string) $data['visibility']);
        }
        if ($dirty !== []) {
            $folder->forceFill($dirty)->save();
        }

        return $this->respondCreated(MediaLibraryFolderResource::make($folder->refresh()));
    }

    public function update(Request $request, MediaLibraryFolder $folder): JsonResponse
    {
        $this->authorize('manage', $folder);

        $data = $this->validate($request, [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        // A move is a distinct domain operation (rebuilds paths + fires an
        // event), so run it through the service rather than a raw column write.
        if (array_key_exists('parent_id', $data)) {
            $newParent = null;
            if ($data['parent_id'] !== null) {
                /** @var MediaLibraryFolder $newParent */
                $newParent = MediaLibraryFolder::query()->findOrFail($data['parent_id']);
                $this->authorize('manage', $newParent);
            }

            $this->library->moveFolderTo($folder, $newParent);
        }

        $dirty = [];
        if (isset($data['name'])) {
            $dirty['name'] = ['en' => (string) $data['name']];
        }
        if (array_key_exists('description', $data)) {
            $dirty['description'] = $data['description'];
        }
        if (isset($data['visibility'])) {
            $dirty['visibility'] = Visibility::from((string) $data['visibility']);
        }
        if ($dirty !== []) {
            $folder->forceFill($dirty)->save();
        }

        return $this->respond(MediaLibraryFolderResource::make($folder->refresh()));
    }

    public function destroy(MediaLibraryFolder $folder): JsonResponse
    {
        $this->authorize('manage', $folder);

        $this->library->trash($folder);

        return $this->respondNoContent();
    }

    /**
     * Share a folder. Requires management rights. Two mutually exclusive modes:
     *
     *  - ACL grant  — pass `subject_type` (user|role|everyone) [+ `subject_value`,
     *    `capability`, `cascade`] to grant a subject a folder capability.
     *  - Share link — otherwise, mint a bearer share-link (`abilities`,
     *    `expires_in`, `invitee_email`).
     */
    public function share(Request $request, MediaLibraryFolder $folder): JsonResponse
    {
        $this->authorize('manage', $folder);

        if ($request->has('subject_type')) {
            $data = $this->validate($request, [
                'subject_type' => ['required', Rule::enum(SubjectType::class)],
                'subject_value' => ['nullable', 'string', 'max:255'],
                'capability' => ['required', Rule::enum(Capability::class)],
                'cascade' => ['sometimes', 'boolean'],
            ]);

            $permission = FolderPermission::create([
                'folder_id' => $folder->getKey(),
                'subject_type' => SubjectType::from((string) $data['subject_type']),
                'subject_value' => $data['subject_value'] ?? null,
                'capability' => Capability::from((string) $data['capability']),
                'cascade' => (bool) ($data['cascade'] ?? true),
            ]);

            return $this->respondCreated([
                'type' => 'acl_grant',
                'permission' => [
                    'id' => $permission->id,
                    'folder_id' => $permission->folder_id,
                    'subject_type' => $permission->subject_type->value,
                    'subject_value' => $permission->subject_value,
                    'capability' => $permission->capability->value,
                    'cascade' => $permission->cascade,
                ],
            ]);
        }

        $data = $this->validate($request, [
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string', Rule::in(['view', 'download'])],
            'expires_in' => ['sometimes', 'integer', 'min:0'],
            'invitee_email' => ['sometimes', 'nullable', 'email'],
        ]);

        /** @var array<int, string> $abilities */
        $abilities = $data['abilities'] ?? ['view'];

        $url = $this->library->shareFolder(
            $folder,
            (int) ($data['expires_in'] ?? 0),
            $abilities,
            $data['invitee_email'] ?? null,
        );

        return $this->respondCreated([
            'type' => 'share_link',
            'url' => $url,
            'abilities' => array_values($abilities),
        ]);
    }
}
