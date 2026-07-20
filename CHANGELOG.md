# Changelog

## [2.2.0] - 2026-07-20

### Added
- **Async extractor pipeline (wired for real).** A queued `ExtractMediaMetadata`
  job (`implements ShouldQueue`) is now dispatched after every successful upload
  / replace. It runs the ordered steps in `media-library.extractors.pipeline`
  over the stored media and persists the results. Dispatch is configurable via
  `media-library.extractors.dispatch` (`queued` default / `sync`), with optional
  `connection` + `queue`.
- **Real `ExifExtractor`.** `DefaultExifExtractor` now extracts image dimensions
  (`getimagesize()`) and full EXIF **including GPS** (`exif_read_data()`),
  persisting to the item's `exif` json column and backfilling `width`/`height`.
  The EXIF read is guarded by `extension_loaded('exif')` and skipped gracefully
  (dimensions still returned) when `ext-exif` is absent.
- **No-op stub defaults.** `NullOcrExtractor`, `NullAiTagger`, and
  `NullScoutAdapter` ship as safe references. OCR / AI-tagging / scout remain
  pluggable extension points (unbound by default, skipped gracefully); bind your
  own engine in `media-library.contracts` to activate a step. The package ships
  no OCR/AI engine.
- `media-library:reextract {item}` now also re-runs the async pipeline inline.

### Changed
- Re-introduced the `extractors` config block and the `contracts.exif` binding
  that the round-2 audit had removed (it was dead because nothing dispatched it).
  This reverses that removal — the pipeline is now dispatched by the upload /
  replace coordinators.

### Security / GDPR
- Extracted EXIF/GPS is stored on the item's `exif` column, so the existing
  `media-library:purge-subject` subject purge already removes it with the item.
  No location PII survives a purge (covered by a regression test).

## [2.1.0] - 2026-07-20

### Added
- **Folder share links now work.** A folder share (`folder_id` set, `item_id`
  null) returns a bounded JSON listing of the folder's items (metadata + URLs)
  instead of a `410`. Capped by `media-library.shares.folder_listing_limit`
  (default 500).
- **GDPR subject purge** — `media-library:purge-subject {type} {id}` hard-deletes
  (and, with `--anonymize-log`, anonymises) all data owned by a subject: items
  and their stored files, folders, versions, variants, attachments, share links,
  tags, saved searches, pending uploads, and the access-log rows for those items.
- **Access-log retention** — `media-library:prune-access-log` deletes entries
  older than `media-library.access_log.prune_after_days` (default 365; `0`
  disables). Scheduled daily.
- **Share token hashing** — new `token_hash` column on `media_library_share_links`;
  tokens are now matched by SHA-256 hash rather than plaintext. Additive
  migration backfills existing rows (`vendor:publish --tag=media-library-migrations`
  + `migrate`).
- **Share route rate limiting** — the public share endpoint now carries a
  `throttle` middleware, configurable via `media-library.routes.share_throttle`
  (default `60,1`).

### Changed
- **`replace()` hardening.** The proxied-upload branch of `ReplaceCoordinator`
  now enforces the same mime allow-list, size limit, and filename sanitisation as
  `UploadCoordinator`, and defaults to the private disk (`local`, was `public`).
  Upload-hardening primitives were extracted into a shared `HardensUploads` trait.
- **Folder ACL is no longer N+1.** `FolderPermissionResolver` loads a folder's
  whole ancestor chain (with permissions) in a constant number of queries via a
  single `whereIn` on the materialised `path`, instead of a lazy depth×2 walk.
- **`moveItems()`** now keeps source/target folder `item_count` counters correct
  in the same transaction and accepts an optional owner to scope the move.

### Removed
- Dead `extractors.sync` / `extractors.async` config (nothing read it) and the
  unused `ExifExtractor` container binding. `blurhash` + `palette` remain the only
  auto-wired synchronous extractors; `exif` / `ocr` / `ai_tagger` / `scout` stay
  pluggable stubs. README/CHANGELOG no longer advertise EXIF/OCR/AI as active
  pipelines. See UPGRADE-2.0.

## [2.0.1] - 2026-07-20

### Added
- Opt-in `media-library.shares.enforce_invitee` flag (default `false`). When
  enabled, a share link that sets `invitee_email` only resolves for a requester
  authenticated as that email (case-insensitive); unauthenticated or mismatched
  requesters get a `403`. The default preserves bearer-token semantics. Bearer
  semantics documented in the controller and README.

### Removed
- Permanently-skipped `GdprAnonymizationTest` stub (the feature it guarded did
  not exist; a real GDPR purge command arrives in 2.1.0).

## [2.0.0] - 2026-07-20

### Changed
- **BREAKING:** the default upload disk is now `local` (private) instead of
  `public`. Item bytes are served through the access-controlled share-link
  controller rather than a guessable `/storage/{id}/{file}` URL. Set
  `MEDIA_LIBRARY_DISK=public` to restore the old public behaviour (which disables
  ACL for direct file access). See UPGRADE-2.0.
- PHP constraint widened to `^8.3`; 8.3 added to the CI matrix.

### Fixed
- Server-proxy `upload()` now validates the real (content-derived) mime + size
  before writing to disk.
- `completeUpload()` re-validates the real object's mime + size instead of
  trusting the client-declared values from `initiateUpload()`.
- Client filenames are sanitised (basename + slug) before S3-key / storage
  filename construction, defending against `../` traversal.
- Share downloads stream via the storage disk for remote (s3) disks instead of
  `getPath()` / `response()->file()`, which only works for local disks.

## [1.1.0] - 2026-05-30

### Added
- Filament admin resources for **Filament v3, v4, and v5**, shipped as three
  parallel resource sets under `src/Filament/V{3,4,5}/` plus a version-dispatching
  `MediaLibraryPlugin::make()` facade that resolves the right set from the
  installed Filament major.
  - **MediaLibraryItemResource** — metadata-only edit form (per-locale
    title/alt/caption/description Tabs, focal-point numeric inputs, folder + tags
    selects, read-only file details + file URL). Table: title, MIME badge, human
    byte size, folder, view/download counts, created_at; MIME + folder filters.
    List + edit pages (items arrive via the facade).
  - **MediaLibraryFolderResource** — per-locale name/description, parent select,
    visibility select, position. Table: name, path, visibility badge, item_count,
    parent.
  - **MediaLibraryTagResource** — per-locale name + colour picker + position.
  - **ShareLinkResource** — read-mostly list + view with a row-level Revoke
    action; no create/edit (links are created through the facade).
- Per-Filament-version PHPStan configs (`phpstan-filament-v{3,4,5}.neon`); the
  base config excludes all three version dirs + the facade.
- CI matrix Filament axis (`3.* / 4.* / 5.*`) running Pint, the matching
  per-major PHPStan config, and Pest per cell.
- Version-guarded Filament smoke tests (`tests/Feature/Filament/V{3,4,5}/`); only
  the installed major's suite runs.

## [1.0.1] - 2026-05-30

### Fixed
- Migrations now publish correctly via `vendor:publish --tag=modules-media-library-migrations`. The previous bare-name `hasMigrations()` list pointed at non-existent source paths (real files are timestamp-prefixed). Switched to `discoversMigrations()`.
- `MediaLibrary::moveFolderTo()` now cascades `path`/`depth` updates to all descendant folders in one pass (previously left descendants with stale paths). Added a guard rejecting moves into a folder's own descendant.

## [1.0.0] - 2026-05-30

### Added
- Initial release of `ozankurt/laravel-modules-media-library`.
- 13 migrations across Catalog / Storage / Sharing / Access.
- `MediaLibraryItem` + `MediaLibraryStorage` host pattern that wraps spatie/laravel-medialibrary as the storage engine.
- Polymorphic owner (User/Team/Organization).
- Polymorphic many-to-many attachments to consumer models with role + position.
- Nested folder tree + per-folder ACL (same shape as ResourceLibrary).
- WordPress-style metadata fields: title, alt_text, caption, description, focal_x/y, dominant_color, palette, blurhash, exif, ai_tags, extracted_text.
- Named-preset + ad-hoc focal-point-aware conversions.
- Server-proxy AND direct-to-S3 presigned uploads.
- Replace-in-place with stable item id + version history (`media_library_versions`).
- Share links with TTL + abilities + access log.
- Tag taxonomy + saved searches per user.
- Pluggable extractor contracts (EXIF / OCR / AI tagger / blurhash / palette / Scout adapter / MediaSubjectResolver).
- Default extractors shipped: EXIF (PHP exif), Blurhash (kornrunner), Palette (Intervention).
- Console commands: prune-versions, prune-variants, rebuild-paths, recount, expire-shares, prune-shares, expire-pending-uploads, reextract, reindex, demo.
- Optional Laravel Notifications + default Blade templates.

### Planned (v1.1)
- Filament v3/v4/v5 admin resources.
- Default Scout adapter.
- Promote cross-module audit log into a shared package.
