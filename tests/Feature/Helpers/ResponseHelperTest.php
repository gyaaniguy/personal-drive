<?php

use App\Helpers\ResponseHelper;
use Illuminate\Http\JsonResponse;
use Tests\Feature\BaseFeatureTest;
use Tests\TestCase;

class ResponseHelperTest extends BaseFeatureTest
{
    public function test_returns_successful_json_response()
    {
        $response = ResponseHelper::json('Success message');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame([
            'status' => true,
            'message' => 'Success message',
        ], $response->getData(true));
    }

    public function test_returns_failed_json_response()
    {
        $response = ResponseHelper::json('Error message', false);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame([
            'status' => false,
            'message' => 'Error message',
        ], $response->getData(true));
    }
}
