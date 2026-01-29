<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\SearchRequest;
use App\Models\LocalFile;
use Inertia\Inertia;
use Inertia\Response;

class SearchFilesController extends Controller
{
    public function index(SearchRequest $request): Response
    {
        $searchQuery = $request->validated('query') ?? '/';

        $files = LocalFile::searchFiles($searchQuery);

        return Inertia::render(
            'Drive/DriveHome', [
            'files' => $files,
            'searchResults' => true,
            ]
        );
    }
}
