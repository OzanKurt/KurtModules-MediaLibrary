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

## Filament admin

Filament v3/v4/v5 admin (WordPress-style grid + edit modal) lands in v1.1.

## License

MIT © Ozan Kurt
