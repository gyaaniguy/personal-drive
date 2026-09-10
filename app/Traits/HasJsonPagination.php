<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

trait HasJsonPagination
{
    protected function paginateJson(LengthAwarePaginator $paginator, string $key = 'data', array $extra = []): JsonResponse
    {
        return response()->json(
            array_merge(
                [
                $key => $paginator->items(),
                'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
                ],
                'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                ],
                ],
                $extra
            )
        );
    }
}
