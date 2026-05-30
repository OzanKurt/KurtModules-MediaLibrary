<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kurt\Modules\MediaLibrary\Sharing\Http\Controllers\ShareLinkController;

Route::middleware('web')
    ->prefix((string) config('media-library.routes.share_prefix', 'media-library/share'))
    ->group(function (): void {
        Route::get('{token}', [ShareLinkController::class, 'show'])->name('media-library.share.show');
    });
