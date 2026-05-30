# Changelog

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
