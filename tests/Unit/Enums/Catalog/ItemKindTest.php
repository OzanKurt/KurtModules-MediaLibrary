<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Enums\ItemKind;

it('has expected cases and values', function () {
    expect(ItemKind::Image->value)->toBe('image');
    expect(ItemKind::Video->value)->toBe('video');
    expect(ItemKind::Audio->value)->toBe('audio');
    expect(ItemKind::Document->value)->toBe('document');
    expect(ItemKind::Archive->value)->toBe('archive');
    expect(ItemKind::Other->value)->toBe('other');
});
