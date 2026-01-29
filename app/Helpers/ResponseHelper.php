<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class ResponseHelper
{
    public static function json(string $message, bool $status = true): JsonResponse
    {
        return response()->json(
            [
            'status' => $status,
            'message' => $message,
            ]
        );
    }
}
