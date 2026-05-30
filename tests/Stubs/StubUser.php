<?php

declare(strict_types=1);

namespace Kurt\Modules\MediaLibrary\Tests\Stubs;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Kurt\Modules\MediaLibrary\Concerns\IsMediaLibraryOwner;
use Kurt\Modules\MediaLibrary\Contracts\MediaLibraryOwner;

final class StubUser extends Model implements Authenticatable, MediaLibraryOwner
{
    use IsMediaLibraryOwner;
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;

    public function getKey(): int|string
    {
        /** @var int|string $key */
        $key = parent::getKey();

        return $key;
    }

    public function getMorphClass(): string
    {
        return parent::getMorphClass();
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
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
}
