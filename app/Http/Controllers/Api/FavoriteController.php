<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListFavoritesRequest;
use App\Http\Requests\DriveRequests\StoreFavoritesRequest;
use App\Services\FavoriteService;
use App\Traits\HasJsonPagination;
use Illuminate\Http\JsonResponse;

class FavoriteController extends Controller
{
    use HasJsonPagination;

    public function __construct(
        private FavoriteService $favoriteService,
    ) {
    }

    public function index(ListFavoritesRequest $request): JsonResponse
    {
        $perPage = $request->validated('per_page', 50);

        $paginator = $this->favoriteService->paginate($perPage);

        return $this->paginateJson($paginator, 'favorites');
    }

    public function store(StoreFavoritesRequest $request): JsonResponse
    {
        $result = $this->favoriteService->store($request->validated('local_file_ids'));

        if (!$result['success']) {
            return ResponseHelper::json($result['message'], false, 422);
        }

        return response()->json(['favorites' => $result['favorites']]);
    }

    public function destroy(string $id): JsonResponse
    {
        $result = $this->favoriteService->remove($id);
        return ResponseHelper::json($result['message']);
    }
}
