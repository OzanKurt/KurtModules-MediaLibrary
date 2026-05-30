<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Enums\AttachmentRole;

it('has expected cases and values', function () {
    expect(AttachmentRole::Cover->value)->toBe('cover');
    expect(AttachmentRole::Social->value)->toBe('social');
    expect(AttachmentRole::Gallery->value)->toBe('gallery');
    expect(AttachmentRole::Thumbnail->value)->toBe('thumbnail');
    expect(AttachmentRole::Attachment->value)->toBe('attachment');
    expect(AttachmentRole::Hero->value)->toBe('hero');
    expect(AttachmentRole::Logo->value)->toBe('logo');
    expect(AttachmentRole::Favicon->value)->toBe('favicon');
});
