# laravel-modules-media-library

WordPress-style media bucket for Laravel SaaS: tenant-aware folders, polymorphic attachments, focal-point conversions, replace-with-stable-id versioning, share links, folder ACL.

Wraps `spatie/laravel-medialibrary` as the storage engine.

## Requirements

- PHP 8.4+
- Laravel 12.x
- `ozankurt/laravel-modules-core` v2.x
- `spatie/laravel-medialibrary` v11.x

## Installation

```bash
composer require ozankurt/laravel-modules-media-library
php artisan vendor:publish --tag=media-library-config
php artisan vendor:publish --tag=media-library-migrations
php artisan migrate
```

## What it provides

- **Catalog** — MediaLibraryFolder (nested tree), MediaLibraryItem (WordPress-style row), MediaLibraryTag, polymorphic attachments to consumer models, saved searches.
- **Storage** — Wraps spatie/laravel-medialibrary via a per-item host model. Versioning with stable item ids. Ad-hoc focal-point-aware variant generation. Presigned direct-to-S3 + server-proxy upload flows.
- **Sharing** — TTL share links with abilities (view/download) + access log + invitee email.
- **Access** — Folder ACL with the same SubjectResolver pattern as ResourceLibrary.
- **Search** — Eloquent scopes (byOwner/byFolder/byTag/byMimeType/byDateRange/search) + optional Scout adapter contract.
- **Pluggable contracts** — EXIF, OCR, AI tagger, blurhash, palette extractor, Scout adapter, MediaSubjectResolver.
- **Optional Laravel Notifications** — Mail + Database channels with publishable Blade templates.

## Share links are bearer credentials

Share links are **bearer credentials by default**: anyone who holds a valid,
unexpired, un-revoked token can view or download the target (subject to the
link's abilities). The `invitee_email` column records who a link was *sent* to
for auditing and UI, but it does **not** restrict access on its own - the token
is the only thing checked.

If you need per-invitee enforcement, set `media-library.shares.enforce_invitee`
to `true` (or `MEDIA_LIBRARY_ENFORCE_INVITEE=true`). With it enabled, a link
that has an `invitee_email` set will only resolve for a requester who is
authenticated as that email address (compared case-insensitively). Unauthenticated
or mismatched requesters receive a `403`. Links whose `invitee_email` is `null`
stay bearer regardless of the flag. The default is `false` to preserve the
non-breaking bearer behavior.

## Filament admin

The package ships admin resources for **Filament v3, v4, and v5**. Register the
version-dispatching plugin on your panel — the correct resource set is resolved
from the installed Filament major automatically:

```php
use Kurt\Modules\MediaLibrary\Filament\MediaLibraryPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(MediaLibraryPlugin::make());
}
```

This registers four resources:

- **MediaLibraryItemResource** — metadata editing (per-locale title / alt text /
  caption / description, focal-point inputs, folder + tags) with a read-only file
  preview. Items are uploaded via the `MediaLibrary` facade, so the admin form
  edits metadata rather than re-uploading; the file URL is shown for reference.
  Table: title, MIME type, human-readable size, folder, view/download counts;
  filterable by MIME type and folder.
- **MediaLibraryFolderResource** — per-locale name/description, parent folder,
  visibility, position. Table: name, path, visibility badge, item count.
- **MediaLibraryTagResource** — per-locale name + colour.
- **ShareLinkResource** — read-only list of share links (token, target,
  abilities, expiry, access count) with a row-level **Revoke** action. Links are
  created through the facade.

`filament/filament` is a dev dependency only; the resources load lazily for
whichever major the consuming app installs.

## License

MIT © Ozan Kurt
