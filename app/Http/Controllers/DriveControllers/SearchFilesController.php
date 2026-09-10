<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\SearchRequest;
use App\Models\LocalFile;
use App\Services\FavoriteService;
use Inertia\Inertia;
use Inertia\Response;

class SearchFilesController extends Controller
{
    public function index(SearchRequest $request, FavoriteService $favoriteService): Response
    {
        $searchQuery = $request->validated('query') ?? '/';

        $files = LocalFile::modifyFileCollectionForDrive(
            LocalFile::searchFiles($searchQuery)->get()
        );

        return Inertia::render(
            'Drive/DriveHome',
            [
            'files' => $files,
            'favorites' => $favoriteService->list(),
            'searchResults' => true,
            ]
        );
    }
}
