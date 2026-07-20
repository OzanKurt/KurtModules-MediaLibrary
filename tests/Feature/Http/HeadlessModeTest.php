<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Lives outside Feature/Http/Api so it inherits the default (headless) TestCase:
// no http.mode override, so the module boots in its safe-by-default mode.

it('registers no API routes in headless mode', function (): void {
    expect(config('media-library.http.mode'))->toBe('headless');

    expect(Route::has('media-library.api.folders.index'))->toBeFalse();
    expect(Route::has('media-library.api.items.index'))->toBeFalse();
});

it('returns 404 for an API path in headless mode', function (): void {
    $this->getJson('/api/media/folders')->assertNotFound();
});
