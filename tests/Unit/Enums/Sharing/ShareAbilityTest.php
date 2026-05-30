<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Sharing\Enums\ShareAbility;

it('has expected cases and values', function () {
    expect(ShareAbility::View->value)->toBe('view');
    expect(ShareAbility::Download->value)->toBe('download');
});
