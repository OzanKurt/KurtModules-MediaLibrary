<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kurt\Modules\MediaLibrary\Sharing\Http\Controllers\ShareLinkController;

$throttle = (string) config('media-library.routes.share_throttle', '60,1');

$middleware = ['web'];
if ($throttle !== '') {
    $middleware[] = 'throttle:'.$throttle;
}

Route::middleware($middleware)
    ->prefix((string) config('media-library.routes.share_prefix', 'media-library/share'))
    ->group(function (): void {
        Route::get('{token}', [ShareLinkController::class, 'show'])->name('media-library.share.show');
    });
