# Upgrade Guide — 2.0

## Breaking change: the default upload disk is now private (`local`)

Before 2.0, `media-library.uploads.disk` defaulted to `public` (via
`MEDIA_LIBRARY_DISK`). Stored bytes were therefore served by spatie at a
guessable `/storage/{id}/{file}` URL that bypassed every share ability, folder
ACL, policy, and access-log check.

As of 2.0 the default is `local` (a **private** disk). Item bytes are served
only through the share-link controller, which enforces share abilities, the
folder ACL, policies, and access logging.

### What you need to do

- **New installs:** nothing. The private default is correct.
- **Existing installs that relied on the public default:**
  - If you *want* access control (recommended), move your media to a private
    disk. Set `MEDIA_LIBRARY_DISK=local` (or a private S3 bucket) and re-point
    existing rows / re-store files on that disk. Serve files via the share
    route rather than linking to `/storage/...`.
  - If you *intentionally* want public, unauthenticated bytes with **no** access
    control, set `MEDIA_LIBRARY_DISK=public` explicitly to restore the old
    behaviour. Understand that this disables share abilities, folder ACL, and
    access logging for direct file access.

The `replace()` flow now defaults to the same private disk and enforces the
same mime allow-list, size limit, and filename sanitisation as the initial
upload, so replacement can no longer be used to bypass those guards.

## New migration in 2.1

2.1 adds a `token_hash` column to `media_library_share_links` (share tokens are
now matched by SHA-256 hash rather than plaintext). Publish and run migrations
after upgrading:

```bash
php artisan vendor:publish --tag=media-library-migrations
php artisan migrate
```

The migration backfills `token_hash` for existing links, so links minted before
the upgrade keep working.

## GDPR / retention

- `media-library:purge-subject {type} {id}` hard-deletes (optionally
  anonymises, with `--anonymize-log`) all media library data owned by a subject.
- `media-library:prune-access-log` enforces access-log retention via
  `media-library.access_log.prune_after_days` (default 365; `0` disables). It is
  scheduled daily alongside the other prune commands.
