<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateShareRequest;
use App\Http\Requests\Api\ListSharesRequest;
use App\Models\Share;
use App\Services\ShareService;
use App\Traits\HasJsonPagination;
use Illuminate\Http\JsonResponse;

class ShareController extends Controller
{
    use HasJsonPagination;

    public function __construct(
        private ShareService $shareService,
    ) {
    }

    public function index(ListSharesRequest $request): JsonResponse
    {
        $perPage = $request->validated('per_page', 50);

        $paginator = Share::getAllUnExpiredQuery()
            ->paginate($perPage);

        return $this->paginateJson($paginator, 'shares');
    }

    public function store(CreateShareRequest $request): JsonResponse
    {
        $result = $this->shareService->create(
            $request->validated('fileList'),
            $request->validated('slug', ''),
            $request->validated('password', ''),
            $request->validated('expiry', ''),
        );

        if (!$result['success']) {
            return ResponseHelper::json($result['message'], false, 422);
        }

        return response()->json([
            'share' => $result['share'],
            'url' => $result['url'],
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->shareService->delete((int) $id);

        return ResponseHelper::json('Share deleted');
    }

    public function toggle(string $id): JsonResponse
    {
        $result = $this->shareService->toggle((int) $id);

        if (!$result['success']) {
            return ResponseHelper::json($result['message'], false, 404);
        }

        return ResponseHelper::json($result['message']);
    }
}
