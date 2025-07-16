<?php

use App\Http\Controllers\AdminControllers;
use App\Http\Controllers\DriveControllers;
use App\Http\Controllers\ShareControllers;
use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\CleanupTempFiles;
use App\Http\Middleware\EnsureFrontendBuilt;
use App\Http\Middleware\HandleAuthOrGuestMiddleware;
use App\Http\Middleware\HandleGuestShareMiddleware;
use App\Http\Middleware\PreventSetupAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', CheckAdmin::class])->group(callback: function () {
    Route::get('/admin-config', [AdminControllers\AdminConfigController::class, 'index'])->name('admin-config');
    Route::post('/admin-config/update', [AdminControllers\AdminConfigController::class, 'update']);
    // Drive routes
    Route::get('/drive/{path?}', [DriveControllers\FileManagerController::class, 'index'])
        ->where('path', '.*')
        ->name('drive');
    Route::post('/upload', [DriveControllers\UploadController::class, 'store'])
        ->middleware(CleanupTempFiles::class)->name('drive.upload');
    Route::post('/create-item', [DriveControllers\UploadController::class, 'createItem'])->middleware(CleanupTempFiles::class);
    Route::post('/delete-files', [DriveControllers\FileDeleteController::class, 'deleteFiles'])->middleware(CleanupTempFiles::class);
    Route::post('/resync', [DriveControllers\ReSyncController::class, 'index']);
    Route::post('/gen-thumbs', [DriveControllers\ThumbnailController::class, 'update']);
    Route::post('/search-files', [DriveControllers\SearchFilesController::class, 'index']);
    Route::get('/search-files', fn () => redirect('/drive'));
    Route::post('/rename-file', [DriveControllers\FileRenameController::class, 'index']);
    Route::post('/abort-replace', [DriveControllers\UploadController::class, 'abortReplace']);
    Route::post('/save-file', [DriveControllers\FileSaveController::class, 'update']);
    Route::post('/move-files', [DriveControllers\MoveFilesController::class, 'update']);

    // Share control Routes
    Route::post('/share-pause', [ShareControllers\ShareFilesModController::class, 'pause']);
    Route::post('/share-delete', [ShareControllers\ShareFilesModController::class, 'delete']);
    Route::post('/share-files', [ShareControllers\ShareFilesGenController::class, 'index']);
    Route::get('/shares-all', [ShareControllers\SharedListController::class, 'index'])->name('shares-all');
});


// admin or shared
Route::get('/fetch-file/{id}/{slug?}', [DriveControllers\FetchFileController::class, 'index'])
    ->middleware([HandleAuthOrGuestMiddleware::class]);
Route::get('/fetch-thumb/{id}/{slug?}', [DriveControllers\FetchFileController::class, 'getThumb'])
    ->middleware([HandleAuthOrGuestMiddleware::class]);
Route::post('/download-files', [DriveControllers\DownloadController::class, 'index'])
    ->middleware([HandleAuthOrGuestMiddleware::class]);

// shared guest routes
Route::post(
    '/shared-check-password',
    [ShareControllers\ShareFilesGuestController::class, 'checkPassword']
)->middleware(['throttle:shared']);
Route::get('/shared-password/{slug}', [ShareControllers\ShareFilesGuestController::class, 'passwordPage'])
    ->name('shared.password')->middleware(['throttle:shared']);
Route::get('/shared/{slug}/{path?}', [ShareControllers\ShareFilesGuestController::class, 'index'])->where(
    'path',
    '.*'
)->middleware([HandleGuestShareMiddleware::class])->name('shared');

// Rejects
Route::get('/error', function (Request $request) {
    return '<h1>Error</h1><p>' . htmlspecialchars($request->query('message', 'An error occurred.')) . '</p>';
})->name('error');
Route::get('/', fn () => redirect('/drive'));
Route::fallback(fn () => redirect('/rejected'));
Route::get(
    '/rejected',
    fn (Request $request) => Inertia::render('Rejected', [
        'message' => $request->query('message', 'No Permission or error'),
    ])
)->name('rejected');

// Setup
Route::middleware([PreventSetupAccess::class])->group(function () {
    Route::get('/setup/account', [AdminControllers\SetupController::class, 'show'])->middleware(EnsureFrontendBuilt::class)->name('setup.account');
    Route::post('/setup/account', [AdminControllers\SetupController::class, 'update']);
    Route::post('/setup/storage', [AdminControllers\AdminConfigController::class, 'update']);
});

require __DIR__ . '/auth.php';
