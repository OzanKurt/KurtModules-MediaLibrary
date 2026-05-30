<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Enums\Visibility;

it('has expected cases and values', function () {
    expect(Visibility::Private->value)->toBe('private');
    expect(Visibility::Restricted->value)->toBe('restricted');
    expect(Visibility::Public->value)->toBe('public');
});
