# ShareLinkController smoke tests

Deferred to Task 24 (cross-cutting integration tests). The controller needs
the share routes file to be loaded, which requires the `MediaLibraryServiceProvider`
(Task 23). Until the provider lands, route resolution + middleware stack are
not wired and any HTTP smoke test would only exercise stub plumbing.

Coverage goals when implemented:

- 200 on valid token + ability=view → streams `response()->file($path)`
- 200 on valid token + ability=download → `Content-Disposition: attachment`
- 403 when requested ability is not in stored abilities
- 404 (or ShareLinkInvalid → handled by exception renderer) for missing token
- 404 / inactive for revoked link
- 404 / inactive for expired link
- 410 when the underlying spatie media is gone
- Access count increments + last_accessed_at populated
- AccessLogEntry row written per request when access_log.enabled=true
