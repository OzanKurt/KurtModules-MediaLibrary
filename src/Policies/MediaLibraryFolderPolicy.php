<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Kurt\Modules\MediaLibrary\Access\Enums\Capability;
use Kurt\Modules\MediaLibrary\Access\Support\MediaLibraryAccess;
use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibraryFolder;

final class MediaLibraryFolderPolicy
{
    public function __construct(private readonly MediaLibraryAccess $access) {}

    public function view(?Authenticatable $user, MediaLibraryFolder $folder): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->access->check($user, $folder, Capability::View);
    }

    public function download(?Authenticatable $user, MediaLibraryFolder $folder): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->access->check($user, $folder, Capability::Download);
    }

    public function manage(?Authenticatable $user, MediaLibraryFolder $folder): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        return $this->access->check($user, $folder, Capability::Manage);
    }

    private function isAdmin(?Authenticatable $user): bool
    {
        return $user !== null && Gate::forUser($user)->allows('canAdminMediaLibrary');
    }
}
