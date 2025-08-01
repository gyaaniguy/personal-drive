<?php

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Helpers\UploadFileHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\BaseFeatureTest;
use Tests\TestCase;

class UploadFileHelperTest extends BaseFeatureTest
{

    public function test_sanitize_path_throws_exception_for_directory_traversal()
    {
        $this->expectException(UploadFileException::class);
        $this->expectExceptionMessage('The upload path or dir contains invalid characters');

        // Use reflection to call the private static method
        $method = new ReflectionMethod(UploadFileHelper::class, 'sanitizePath');
        $method->setAccessible(true);
        $method->invoke(null, '../invalid/path');
    }
}
