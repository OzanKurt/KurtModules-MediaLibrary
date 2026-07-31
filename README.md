# laravel-modules-media-library

[![tests](https://github.com/OzanKurt/laravel-modules-media-library/actions/workflows/tests.yml/badge.svg)](https://github.com/OzanKurt/laravel-modules-media-library/actions/workflows/tests.yml)

WordPress-style media bucket for Laravel SaaS: tenant-aware folders, polymorphic attachments, focal-point conversions, replace-with-stable-id versioning, share links, folder ACL.

Wraps `spatie/laravel-medialibrary` as the storage engine.

## Requirements

- PHP `^8.4`
- Laravel `^13.0`
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

## API

The package ships an out-of-the-box JSON REST API built on the Core **API kit**.
It is **safe by default**: in `headless` mode (the default) nothing is
registered — no routes, no rate limiter. Opt in per environment:

```dotenv
MEDIA_LIBRARY_HTTP_MODE=api
```

or in `config/media-library.php`:

```php
'http' => [
    'mode' => env('MEDIA_LIBRARY_HTTP_MODE', 'headless'), // headless | api | ui
    'prefix' => 'api/media',
    'middleware' => ['api'],
    'auth_middleware' => ['auth'],   // e.g. ['auth:sanctum'] for token auth
    'rate_limit' => '60,1',          // maxAttempts,decayMinutes
],
```

Every route is throttled by the named `media-library-api` limiter (keyed by user
id, or client IP for guests). Read routes get the base middleware; **write**
routes additionally get `auth_middleware`.

### Endpoints

All paths are under the configured prefix (default `api/media`). Route names are
prefixed `media-library.api.` (e.g. `media-library.api.folders.index`).

| Method | Path | Name | Auth | Purpose |
|---|---|---|---|---|
| GET | `folders` | `folders.index` | read | ACL-scoped folder list — `?parent={id}` lists that folder's children (needs view on the parent), else the current owner's roots |
| GET | `folders/{folder}` | `folders.show` | read | Show a folder (view) |
| POST | `folders` | `folders.store` | write | Create a folder (`name`, optional `parent_id`/`description`/`visibility`) |
| PATCH | `folders/{folder}` | `folders.update` | write | Rename / move (`parent_id`) / re-scope a folder |
| DELETE | `folders/{folder}` | `folders.destroy` | write | Soft-delete a folder |
| POST | `folders/{folder}/share` | `folders.share` | write | Share a folder — **ACL grant** (`subject_type`, `subject_value`, `capability`, `cascade`) or **bearer share-link** (`abilities`, `expires_in`, `invitee_email`) |
| GET | `items` | `items.index` | read | ACL-scoped item list — `?folder={id}` lists a folder's items (needs view on the folder), else the current owner's unfiled items |
| GET | `items/{item}` | `items.show` | read | Show an item (view) |
| GET | `items/{item}/download` | `items.download` | read* | Stream bytes — accepts a valid signature (from `signed-url`) **or** ACL download rights |
| POST | `items` | `items.store` | write | **Upload** an image/file (multipart `file`, optional `folder_id` + metadata) — server-proxy, runs through the `UploadCoordinator` |
| PATCH | `items/{item}` | `items.update` | write | Rename / move (`folder_id`) / edit metadata |
| DELETE | `items/{item}` | `items.destroy` | write | Soft-delete an item |
| POST | `items/{item}/replace` | `items.replace` | write | Replace the file (stable id) via the `ReplaceCoordinator` (multipart `file`, optional `changelog`) |
| GET | `items/{item}/signed-url` | `items.signed-url` | write | Mint a time-limited signed URL to `download` (needs download rights) |
| POST | `uploads` | `uploads.initiate` | write | Begin a presigned direct-to-storage upload |
| POST | `uploads/{uploadId}/complete` | `uploads.complete` | write | Finalize a presigned upload |
| DELETE | `uploads/{uploadId}` | `uploads.cancel` | write | Cancel a pending presigned upload |

Responses use the Core envelope: `{ "data": … }` for success (with
`meta.pagination` on list endpoints), `{ "message": …, "errors": … }` for
errors. Lists accept `?per_page=` (clamped to 100), `?page=`, `?sort=` and
`?filter[field]=` (allow-listed per resource).

### ACL enforcement

Reads are **ACL-scoped**: every controller authorises the folder/item Policy, so
a record behind an ACL the caller lacks is never returned (an item in a folder
the subject cannot access is neither shown nor listed). Writes require the auth
middleware **and** pass the same folder-ACL Policies per method — authentication
alone is never sufficient. Folder ACL is the model's own `FolderPermission` +
visibility layer (`view` / `download` / `manage` capabilities, cascading down
the tree).

### Uploads go through the coordinators

`items` (upload) and `items/{item}/replace` are thin adapters over the existing
`UploadCoordinator` / `ReplaceCoordinator` — the API never writes media directly.
That means every API upload runs the same content-mime + size validation,
filename sanitisation, spatie storage, synchronous placeholder extraction
(blurhash/palette) **and** the queued `ExtractMediaMetadata` pipeline
(EXIF/GPS, OCR, AI tags, search index) that the facade path runs, plus the same
GDPR bookkeeping. The default **server-proxy** flow POSTs the file to `items`.

For a **presigned direct-to-storage** flow (skip the proxy, upload straight to
S3), call `POST uploads` to get a presigned PUT URL + headers + object key, PUT
the bytes to it from the client, then `POST uploads/{uploadId}/complete` — the
finalize step re-validates the real object and runs the identical coordinator
path. `initiate`/`complete`/`cancel` are scoped to the owner that created the
pending upload.

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

## Testing

```bash
composer install
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=2G
vendor/bin/pest
```

CI runs the same checks on every push and pull request
(`.github/workflows/tests.yml`), against PHP 8.4 / Laravel 13. Static analysis
is held at **PHPStan level 8**; the suite runs on **Pest 5**.

## License

MIT © Ozan Kurt
