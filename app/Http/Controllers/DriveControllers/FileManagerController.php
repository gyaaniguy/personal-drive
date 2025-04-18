<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Models\LocalFile;
use Inertia\Inertia;
use Inertia\Response;
use App\Http\Requests\DriveRequests\FileManagerRequest;

class FileManagerController extends Controller
{
    public function index(FileManagerRequest $request): Response
    {
        $path = $request->validated('path') ?? '';

        $files = LocalFile::getFilesForPublicPath($path);

        return Inertia::render('Drive/DriveHome', [
            'files' => $files,
            'path' => '/drive'.($path ? '/'.$path : ''),
            'token' => csrf_token(),
        ]);
    }

}
