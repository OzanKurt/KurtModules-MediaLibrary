# Changelog

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
