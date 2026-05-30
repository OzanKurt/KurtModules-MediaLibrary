<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Access\Enums\Capability;

it('has expected cases and values', function () {
    expect(Capability::View->value)->toBe('view');
    expect(Capability::Download->value)->toBe('download');
    expect(Capability::Manage->value)->toBe('manage');
});

it('ranks capabilities in ascending order', function () {
    expect(Capability::View->rank())->toBe(1);
    expect(Capability::Download->rank())->toBe(2);
    expect(Capability::Manage->rank())->toBe(3);
    expect(Capability::View->rank())->toBeLessThan(Capability::Download->rank());
    expect(Capability::Download->rank())->toBeLessThan(Capability::Manage->rank());
});
