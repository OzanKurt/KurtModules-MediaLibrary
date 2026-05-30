<?php

declare(strict_types=1);

/**
 * TODO(v1.x): GDPR / DSGVO anonymization helpers are not part of MediaLibrary v1.
 * The spec defers them to the Events module (KurtModules-Events) where the
 * personal-data inventory + erasure flow is being designed end-to-end.
 *
 * When MediaLibrary picks them up, this file should exercise:
 *   - Anonymizing uploader/creator references on items + folders + share links
 *     when the underlying user account is forgotten.
 *   - Detaching personal-data fields on AccessLogEntry rows tied to the user.
 *   - Leaving non-personal metadata (filename, mime_type, sha) intact.
 *
 * For now, mark the placeholder so the suite remains honest about coverage.
 */
it('TODO: GDPR anonymization helpers will land alongside Events v1.x', function (): void {
    expect(true)->toBeTrue();
})->skip('GDPR anonymization is not part of MediaLibrary v1 — tracked in Events module.');
