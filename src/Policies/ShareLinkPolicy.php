<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Support\MediaLibraryAccess;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryItem;
use Kurt\Modules\MediaLibrary\Sharing\Models\ShareLink;

final class ShareLinkPolicy
{
    public function __construct(private readonly MediaLibraryAccess $access) {}

    public function create(Authenticatable $user, MediaLibraryItem|MediaLibraryFolder $target): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->access->check($user, $target, Capability::Manage);
    }

    public function revoke(Authenticatable $user, ShareLink $link): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if ($link->created_by === $user->getAuthIdentifier()) {
            return true;
        }

        $target = $link->item ?? $link->folder;

        return $target !== null && $this->access->check($user, $target, Capability::Manage);
    }

    private function isAdmin(Authenticatable $user): bool
    {
        return Gate::forUser($user)->allows('canAdminMediaLibrary');
    }
}
