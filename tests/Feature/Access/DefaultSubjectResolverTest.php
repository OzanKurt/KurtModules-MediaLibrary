<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Kurt\Modules\MediaLibrary\Access\Enums\SubjectType;
use Kurt\Modules\MediaLibrary\Access\Support\DefaultSubjectResolver;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Exceptions\OwnerNotResolved;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

it('returns [Everyone] when no user is provided', function (): void {
    $resolver = new DefaultSubjectResolver;

    $subjects = $resolver->subjects(null);

    expect($subjects)->toHaveCount(1);
    expect($subjects[0]->type)->toBe(SubjectType::Everyone);
    expect($subjects[0]->value)->toBeNull();
});

it('returns [Everyone, User($id)] when a user is provided', function (): void {
    $user = new StubUser;
    $user->setRawAttributes(['id' => 42], sync: true);
    $user->exists = true;

    $resolver = new DefaultSubjectResolver;

    $subjects = $resolver->subjects($user);

    expect($subjects)->toHaveCount(2);
    expect($subjects[0]->type)->toBe(SubjectType::Everyone);
    expect($subjects[1]->type)->toBe(SubjectType::User);
    expect($subjects[1]->value)->toBe('42');
});

it('throws OwnerNotResolved when defaultOwner receives a non-MediaLibraryOwner user', function (): void {
    // Anonymous Authenticatable that does NOT implement MediaLibraryOwner.
    $user = new class implements Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 42;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }
    };

    $resolver = new DefaultSubjectResolver;

    expect(fn () => $resolver->defaultOwner($user))->toThrow(OwnerNotResolved::class);
});

it('throws OwnerNotResolved when defaultOwner receives null', function (): void {
    $resolver = new DefaultSubjectResolver;

    expect(fn () => $resolver->defaultOwner(null))->toThrow(OwnerNotResolved::class);
});

it('returns the user when it implements MediaLibraryOwner', function (): void {
    $owner = new class implements Authenticatable, MediaLibraryOwner
    {
        public function getKey(): int|string
        {
            return 99;
        }

        public function getMorphClass(): string
        {
            return 'stub_owner';
        }

        public function getMediaLibraryDisplayName(): string
        {
            return 'Stub Owner';
        }

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return 99;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): string
        {
            return '';
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return '';
        }
    };

    $resolver = new DefaultSubjectResolver;

    expect($resolver->defaultOwner($owner))->toBe($owner);
});
