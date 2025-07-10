<?php

namespace Tests\Unit\Validation;

use App\Http\Requests\CommonRequest;
use Tests\TestCase;
use Validator;

class CommonRequestTest extends TestCase
{
    public function testSlugRules()
    {
        $rules = CommonRequest::slugRules();

        $validator = Validator::make(['slug' => 'valid-slug'], ['slug' => $rules]);
        $this->assertTrue($validator->passes(), 'A valid slug should pass validation.');

        $validator = Validator::make(['slug' => 'invalid slug'], ['slug' => $rules]);
        $this->assertFalse($validator->passes(), 'A slug with spaces should fail validation.');

        $validator = Validator::make(['slug' => 'toolongslugtoolongslug'], ['slug' => $rules]);
        $this->assertFalse($validator->passes(), 'A slug exceeding 20 characters should fail validation.');

        $validator = Validator::make(['slug' => ''], ['slug' => $rules]);
        $this->assertFalse($validator->passes(), 'An empty slug should fail validation.');

        $validator = Validator::make(['slug' => 'valid_slug123'], ['slug' => $rules]);
        $this->assertTrue($validator->passes(), 'A valid slug with underscores and numbers should pass validation.');
    }

    public function testPathRules()
    {
        $rules = CommonRequest::pathRules();

        $validator = Validator::make(['path' => 'd:/documents folder/storage'], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'Windows valid path should pass validation.');

        $validator = Validator::make(['path' => '/valid/path'], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'The valid path should pass validation.');

        $validator = Validator::make(['path' => str_repeat('a', 101)], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'A path exceeding 100 characters should fail validation.');

        $validator = Validator::make(['path' => null], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'A null path should pass validation as it is nullable.');

        $validator = Validator::make(['path' => 'invalid|path'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'A path with invalid characters should fail validation.');

        $validator = Validator::make(['path' => '/var/www/../naughty'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'A path with invalid characters should fail validation.');
    }

    public function testPasswordRules()
    {
        $rules = CommonRequest::passwordRules();

        $validator = Validator::make(['password' => 'StrongPass123!'], ['password' => $rules]);
        $this->assertTrue($validator->passes(), 'A strong password should pass validation.');

        $validator = Validator::make(['password' => 'weak'], ['password' => $rules]);
        $this->assertFalse($validator->passes(), 'A weak password should fail validation.');

        $validator = Validator::make(['password' => ''], ['password' => $rules]);
        $this->assertFalse($validator->passes(), 'An empty password should fail validation.');
    }

    public function testFileListRules()
    {
        $rules = CommonRequest::fileListRules();

        $validator = Validator::make(['fileList' => ['01F8MECHZX3TBDSZ7XRADM79XE']], $rules);
        $this->assertTrue($validator->passes(), 'A valid file list with ULIDs should pass validation.');

        $validator = Validator::make(['fileList' => ['invalid-ulid']], $rules);
        $this->assertFalse($validator->passes(), 'A file list with an invalid ULID should fail validation.');

        $validator = Validator::make(['fileList' => []], $rules);
        $this->assertFalse($validator->passes(), 'An empty file list should fail validation.');

        $validator = Validator::make(['fileList' => null], $rules);
        $this->assertFalse($validator->passes(), 'A null file list should fail validation.');

        $validator = Validator::make(['fileList' => ['01F8MECHZX3TBDSZ7XRADM79XE', '01F8MECHZX3TBDSZ7XRADM79XF']],
            $rules);
        $this->assertTrue($validator->passes(), 'A valid file list with multiple ULIDs should pass validation.');
    }

    public function testUsernameRules()
    {
        $rules = CommonRequest::usernameRules();

        $validator = Validator::make(['username' => 'valid_username123'], ['username' => $rules]);
        $this->assertTrue($validator->passes(), 'A valid username should pass validation.');

        $validator = Validator::make(['username' => 'Invalid-Username'], ['username' => $rules]);
        $this->assertFalse($validator->passes(), 'A username with invalid characters should fail validation.');

        $validator = Validator::make(['username' => ''], ['username' => $rules]);
        $this->assertFalse($validator->passes(), 'An empty username should fail validation.');

        $validator = Validator::make(['username' => '12345'], ['username' => $rules]);
        $this->assertTrue($validator->passes(), 'A numeric username should pass validation.');
    }

    public function testItemNameRule()
    {
        $rules = CommonRequest::itemNameRule();

        $validator = Validator::make(['itemName' => 'Valid Item Name 123'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'A valid item name should pass validation.');

        $validator = Validator::make(['itemName' => str_repeat('a', 256)], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'An item name exceeding 255 characters should fail validation.');

        $validator = Validator::make(['itemName' => 'Invalid|ItemName'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'An item name with invalid characters should fail validation.');

        $validator = Validator::make(['itemName' => ''], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'An empty item name should fail validation.');
    }

    public function testLocalFileIdRules()
    {
        $rules = CommonRequest::localFileIdRules();

        $validator = Validator::make(['localFileId' => '01F8MECHZX3TBDSZ7XRADM79XE'], ['localFileId' => $rules]);
        $this->assertTrue($validator->passes(), 'A valid ULID should pass validation.');

        $validator = Validator::make(['localFileId' => 'invalid-ulid'], ['localFileId' => $rules]);
        $this->assertFalse($validator->passes(), 'An invalid ULID should fail validation.');

        $validator = Validator::make(['localFileId' => ''], ['localFileId' => $rules]);
        $this->assertFalse($validator->passes(), 'An empty ULID should fail validation.');
    }

    public function testSharePasswordRules()
    {
        $rules = CommonRequest::sharePasswordRules();

        $validator = Validator::make(['sharePassword' => 'StrongPass123!'], ['sharePassword' => $rules]);
        $this->assertTrue($validator->passes(), 'A strong password should pass validation.');

        $validator = Validator::make(['sharePassword' => 'short'], ['sharePassword' => $rules]);
        $this->assertFalse($validator->passes(), 'A password shorter than 6 characters should fail validation.');

        $validator = Validator::make(['sharePassword' => null], ['sharePassword' => $rules]);
        $this->assertTrue($validator->passes(), 'A null password should pass validation as it is nullable.');
    }
}
