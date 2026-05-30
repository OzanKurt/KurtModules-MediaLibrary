<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Sharing\Enums\AccessAction;

it('has expected cases and values', function () {
    expect(AccessAction::View->value)->toBe('view');
    expect(AccessAction::Download->value)->toBe('download');
    expect(AccessAction::Upload->value)->toBe('upload');
    expect(AccessAction::Replace->value)->toBe('replace');
    expect(AccessAction::Delete->value)->toBe('delete');
});
