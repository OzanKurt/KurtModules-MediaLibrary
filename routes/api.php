<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Kurt\Modules\MediaLibrary\Http\Api\Controllers\FolderController;
use Kurt\Modules\MediaLibrary\Http\Api\Controllers\MediaItemController;

/*
|--------------------------------------------------------------------------
| Media Library REST API
|--------------------------------------------------------------------------
|
| This file is loaded by PackageServiceProvider::registerModuleApi(), already
| wrapped in the module's read route group (prefix `api/media`, base
| middleware, `media-library-api` throttle, `media-library.api.` name prefix).
| Only headless mode leaves it unregistered.
|
| Reads below are ACL-scoped: every controller authorises the folder/item
| Policy so a record behind an ACL the caller lacks is never returned. Writes
| append the module auth middleware AND run the same Policies per method, so
| authentication alone is never sufficient — folder-ACL still gates the action.
|
*/

$auth = config('media-library.http.auth_middleware', ['auth']);

// Read (ACL-scoped) endpoints — base middleware + throttle only.
Route::get('folders', [FolderController::class, 'index'])->name('folders.index');
Route::get('folders/{folder}', [FolderController::class, 'show'])->name('folders.show');

Route::get('items', [MediaItemController::class, 'index'])->name('items.index');
Route::get('items/{item}', [MediaItemController::class, 'show'])->name('items.show');
// Accepts either a valid signature (from items.signed-url) or download rights.
Route::get('items/{item}/download', [MediaItemController::class, 'download'])->name('items.download');

// Write endpoints — auth middleware appended per route; Policies still enforced.
Route::post('folders', [FolderController::class, 'store'])->middleware($auth)->name('folders.store');
Route::patch('folders/{folder}', [FolderController::class, 'update'])->middleware($auth)->name('folders.update');
Route::delete('folders/{folder}', [FolderController::class, 'destroy'])->middleware($auth)->name('folders.destroy');
Route::post('folders/{folder}/share', [FolderController::class, 'share'])->middleware($auth)->name('folders.share');

Route::post('items', [MediaItemController::class, 'store'])->middleware($auth)->name('items.store');
Route::patch('items/{item}', [MediaItemController::class, 'update'])->middleware($auth)->name('items.update');
Route::delete('items/{item}', [MediaItemController::class, 'destroy'])->middleware($auth)->name('items.destroy');
Route::post('items/{item}/replace', [MediaItemController::class, 'replace'])->middleware($auth)->name('items.replace');
Route::get('items/{item}/signed-url', [MediaItemController::class, 'signedUrl'])->middleware($auth)->name('items.signed-url');

// Presigned direct-to-storage upload flow.
Route::post('uploads', [MediaItemController::class, 'initiateUpload'])->middleware($auth)->name('uploads.initiate');
Route::post('uploads/{uploadId}/complete', [MediaItemController::class, 'completeUpload'])->middleware($auth)->name('uploads.complete');
Route::delete('uploads/{uploadId}', [MediaItemController::class, 'cancelUpload'])->middleware($auth)->name('uploads.cancel');
