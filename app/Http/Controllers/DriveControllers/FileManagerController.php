<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Models\LocalFile;
use App\Services\FavoriteService;
use App\Services\PathService;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Requests\DriveRequests\FileManagerRequest;

class FileManagerController extends Controller
{
    public function index(FileManagerRequest $request, PathService $pathService, FavoriteService $favoriteService): Response
    {
        $path = $request->validated('path') ?? '';

        $files = LocalFile::filesForDrive($path);

        return Inertia::render(
            'Drive/DriveHome',
            [
            'files' => $files,
            'favorites' => $favoriteService->list(),
            'path' => '/drive' . ($path ? '/' . $path : ''),
            'token' => csrf_token(),
            'folderExists' => $path === '' || is_dir($pathService->genPrivatePathFromPublic($path)),
            ]
        );
    }
}
