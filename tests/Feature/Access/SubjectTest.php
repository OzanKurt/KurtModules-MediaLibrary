<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Values\Subject;

it('matches Everyone rows regardless of value', function () {
    $subject = new Subject(SubjectType::Everyone, null);

    expect($subject->matches('everyone', null))->toBeTrue();
    expect($subject->matches('everyone', 'anything'))->toBeTrue();
});

it('matches User rows when the value matches', function () {
    $subject = new Subject(SubjectType::User, '42');

    expect($subject->matches('user', '42'))->toBeTrue();
    expect($subject->matches('user', '99'))->toBeFalse();
});

it('matches Role rows when the value matches', function () {
    $subject = new Subject(SubjectType::Role, 'editor');

    expect($subject->matches('role', 'editor'))->toBeTrue();
    expect($subject->matches('role', 'viewer'))->toBeFalse();
});

it('does not match when the row type differs', function () {
    $subject = new Subject(SubjectType::User, '42');

    expect($subject->matches('role', '42'))->toBeFalse();
    expect($subject->matches('everyone', null))->toBeFalse();
});
