# laravel-modules-media-library

WordPress-style media bucket for Laravel SaaS: tenant-aware folders, polymorphic attachments, focal-point conversions, replace-with-stable-id versioning, share links, folder ACL.

Wraps `spatie/laravel-medialibrary` as the storage engine.

## Requirements

- PHP 8.3+
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
- **Sharing** — TTL share links with abilities (view/download) + access log + invitee email. Item shares stream the file; folder shares return a bounded JSON listing of the folder's items. The public share route is rate-limited and tokens are matched by hash, not plaintext.
- **Access** — Folder ACL with the same SubjectResolver pattern as ResourceLibrary.
- **Search** — Eloquent scopes (byOwner/byFolder/byTag/byMimeType/byDateRange/search) + optional Scout adapter contract.
- **Two-stage metadata extraction** — dimensions, blurhash, and colour palette are extracted synchronously in-request on upload (immediate placeholder + colour data). A second, **async** stage (the `ExtractMediaMetadata` job) then runs the configured extractor pipeline over the stored file: real EXIF/GPS + dimensions via `exif_read_data()`/`getimagesize()`, plus pluggable OCR / AI-tagging / search-indexing steps. See [Extraction pipeline](#extraction-pipeline). `MediaSubjectResolver` is pluggable via config.
- **GDPR helpers** — `media-library:purge-subject {type} {id}` hard-deletes (optionally anonymises) all data owned by a subject; `media-library:prune-access-log` enforces access-log retention (`access_log.prune_after_days`).
- **Optional Laravel Notifications** — Mail + Database channels with publishable Blade templates.

## Extraction pipeline

After a successful upload or replace, the coordinators dispatch the
`ExtractMediaMetadata` job (`implements ShouldQueue`). It resolves the item's
stored media and runs the ordered steps in `media-library.extractors.pipeline`,
each mapped to a contract binding in `media-library.contracts`:

| Step | Contract | Default binding | Persists to |
| --- | --- | --- | --- |
| `exif` | `Storage\Contracts\ExifExtractor` | `DefaultExifExtractor` (real) | `exif` json (+ backfills `width`/`height`) |
| `ocr` | `Storage\Contracts\OcrExtractor` | `null` (skipped) | `extracted_text` |
| `ai_tagger` | `Storage\Contracts\AiTagger` | `null` (skipped) | `ai_tags` json |
| `scout` | `Search\Contracts\ScoutAdapter` | `null` (skipped) | search index |

`DefaultExifExtractor` uses PHP's `getimagesize()` for dimensions and
`exif_read_data()` for full EXIF **including GPS**. The EXIF call is guarded by
`function_exists('exif_read_data')` / `extension_loaded('exif')`: if `ext-exif`
is unavailable it is skipped gracefully and only dimensions are returned. Each
step also fires a domain event (`ExifExtracted`, `TextExtracted`,
`AiTagsAssigned`) the consumer can listen for.

**Extension points.** The package ships **no** OCR / AI / search engine — those
steps stay unbound (and are skipped) until you supply one. Point the config at
your implementation:

```php
// config/media-library.php
'contracts' => [
    'ocr'       => \App\Media\TesseractOcr::class,     // implements Storage\Contracts\OcrExtractor
    'ai_tagger' => \App\Media\VisionTagger::class,     // implements Storage\Contracts\AiTagger
    'scout'     => \App\Media\ScoutIndexer::class,     // implements Search\Contracts\ScoutAdapter
],
```

Safe no-op reference stubs (`NullOcrExtractor`, `NullAiTagger`,
`NullScoutAdapter`) ship with the package if you want an inert binding.

**Dispatch mode.** `media-library.extractors.dispatch` (env
`MEDIA_LIBRARY_EXTRACTORS_DISPATCH`) is `queued` by default; set it to `sync` to
run the pipeline inline in the request. `extractors.connection` /
`extractors.queue` route the queued job. `media-library:reextract {item}`
re-runs both stages for a single item on demand.

> **Note:** this reverses the round-2 audit removal of the `extractors.async`
> config, which was dropped because nothing dispatched it. The pipeline is now
> wired for real via the `ExtractMediaMetadata` job.

**GDPR.** Extracted EXIF/GPS lives in the item's `exif` column, so
`media-library:purge-subject {type} {id}` removes it together with the item —
no orphaned location PII survives a subject purge.

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
