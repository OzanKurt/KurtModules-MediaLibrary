<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Http\Api\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Kurt\Modules\Core\Http\Controllers\ApiController;
use Kurt\Modules\MediaLibrary\Access\Contracts\MediaSubjectResolver;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;

/**
 * Shared base for the media-library REST controllers.
 *
 * Adds ACL-aware helpers on top of the Core API kit: resolving the current
 * subject as an owner for writes, filtering a result set down to the records a
 * subject may actually see (so a folder/item behind an ACL the caller lacks is
 * never leaked), and paginating an already-filtered in-memory collection.
 */
abstract class MediaApiController extends ApiController
{
    /**
     * Resolve the authenticated user as a media-library owner for writes.
     * Returns null when the user cannot own media (the caller decides how to
     * respond — typically a 422/403).
     */
    protected function resolveOwner(Request $request): ?MediaLibraryOwner
    {
        $user = $request->user();

        if (! $user instanceof MediaLibraryOwner) {
            return null;
        }

        // Normalise through the configured resolver so a host app can override
        // "who owns uploads" (e.g. a tenant rather than the user) in one place.
        /** @var MediaSubjectResolver $subjects */
        $subjects = app(MediaSubjectResolver::class);

        return $subjects->defaultOwner($user);
    }

    /**
     * Keep only the records the current user is authorised for under $ability.
     * ACL scoping happens here in PHP because folder capability resolution walks
     * the ancestry chain and cannot be expressed as a single SQL predicate.
     *
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $records
     * @return Collection<int, TModel>
     */
    protected function filterAuthorised(Collection $records, string $ability): Collection
    {
        /** @var Collection<int, TModel> $authorised */
        $authorised = $records
            ->filter(static fn (Model $record): bool => Gate::allows($ability, $record))
            ->values();

        return $authorised;
    }

    /**
     * Paginate an already-filtered collection with the same `?per_page=` /
     * `?page=` semantics as the Core kit's query paginator.
     *
     * @template TModel
     *
     * @param  Collection<int, TModel>  $records
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function paginateCollection(Collection $records, Request $request, int $default = 15, int $max = 100): LengthAwarePaginator
    {
        $perPage = $request->query('per_page');
        $perPage = is_numeric($perPage) ? (int) $perPage : $default;
        $perPage = max(1, min($perPage, $max));

        $page = $request->query('page');
        $page = is_numeric($page) ? max(1, (int) $page) : 1;

        $items = $records->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $records->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
