<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;

it('has expected cases and values', function () {
    expect(SubjectType::User->value)->toBe('user');
    expect(SubjectType::Role->value)->toBe('role');
    expect(SubjectType::Everyone->value)->toBe('everyone');
});
