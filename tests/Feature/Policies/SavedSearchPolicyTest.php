<?php

declare(strict_types=1);

use Kurt\Modules\MediaLibrary\Catalog\Models\MediaLibrarySavedSearch;
use Kurt\Modules\MediaLibrary\Policies\SavedSearchPolicy;
use Kurt\Modules\MediaLibrary\Tests\Stubs\StubUser;

beforeEach(function (): void {
    $this->policy = new SavedSearchPolicy;
});

function savedSearchPolicyStubUser(int $id): StubUser
{
    $user = new StubUser;
    $user->setRawAttributes(['id' => $id], sync: true);
    $user->exists = true;

    return $user;
}

it('grants view to the owner of the saved search', function (): void {
    $owner = savedSearchPolicyStubUser(7);
    $search = MediaLibrarySavedSearch::factory()->create(['user_id' => 7]);

    expect($this->policy->view($owner, $search))->toBeTrue();
});

it('denies view for a non-owner', function (): void {
    $other = savedSearchPolicyStubUser(99);
    $search = MediaLibrarySavedSearch::factory()->create(['user_id' => 7]);

    expect($this->policy->view($other, $search))->toBeFalse();
});

it('mirrors view for the delete capability', function (): void {
    $owner = savedSearchPolicyStubUser(7);
    $other = savedSearchPolicyStubUser(99);
    $search = MediaLibrarySavedSearch::factory()->create(['user_id' => 7]);

    expect($this->policy->delete($owner, $search))->toBeTrue();
    expect($this->policy->delete($other, $search))->toBeFalse();
});
