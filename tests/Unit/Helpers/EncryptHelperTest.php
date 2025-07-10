<?php

namespace Tests\Unit\Helpers;

use App\Helpers\EncryptHelper;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class EncryptHelperTest extends TestCase
{
    public function test_encrypt_returns_a_string()
    {
        $encryptedText = EncryptHelper::encrypt('hello world');
        $this->assertIsString($encryptedText);
        $this->assertNotEmpty($encryptedText);
    }

    public function test_decrypt_can_decrypt_encrypted_string()
    {
        $originalText = 'this is a secret message';
        $encryptedText = EncryptHelper::encrypt($originalText);
        $decryptedText = EncryptHelper::decrypt($encryptedText);
        $this->assertEquals($originalText, $decryptedText);
    }

    public function test_decrypt_throws_exception_for_invalid_string()
    {
        $this->expectException(DecryptException::class);
        EncryptHelper::decrypt('invalid-encrypted-string');
    }
}
