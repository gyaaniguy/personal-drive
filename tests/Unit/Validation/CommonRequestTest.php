<?php

namespace Tests\Unit\Validation;

use App\Http\Requests\CommonRequest;
use Tests\TestCase;
use Validator;

class CommonRequestTest extends TestCase
{
    public function testSlugRules()
    {
        $rules = CommonRequest::shareSlugRules();

        $validator = Validator::make(['slug' => 'γνώση-multi'], ['slug' => $rules]);
        $this->assertTrue($validator->passes(), 'A multi-lingual slug should pass validation.');

        $validator = Validator::make(['slug' => 'valid-slug'], ['slug' => $rules]);
        $this->assertTrue($validator->passes(), 'A valid slug should pass validation.');

        $validator = Validator::make(['slug' => 'invalid slug'], ['slug' => $rules]);
        $this->assertFalse($validator->passes(), 'A slug with spaces should fail validation.');

        $validator = Validator::make(['slug' => 'toolongslugtoolongslug'], ['slug' => $rules]);
        $this->assertFalse($validator->passes(), 'A slug exceeding 20 characters should fail validation.');

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

        $validator = Validator::make(['path' => str_repeat('a', 513)], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'A path exceeding 512 characters should fail validation.');

        $validator = Validator::make(['path' => null], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'A null path should pass validation as it is nullable.');

        $validator = Validator::make(['path' => 'invalid|path'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'A path with invalid characters should fail validation.');

        $validator = Validator::make(['path' => '/var/www/../naughty'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'A path with invalid characters should fail validation.');

        // --- Existing tests ---
        $validator = Validator::make(['path' => 'd:/documents folder/storage'], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'Windows valid path should pass validation.');

        $validator = Validator::make(['path' => '/valid/path'], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'The valid path should pass validation.');

        $validator = Validator::make(['path' => str_repeat('a', 513)], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'A path exceeding 512 characters should fail validation.');

        $validator = Validator::make(['path' => null], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'A null path should pass validation as it is nullable.');

        $validator = Validator::make(['path' => 'invalid|path'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'A path with invalid characters should fail validation.');

        $validator = Validator::make(['path' => '/var/www/../naughty'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'A path with invalid characters should fail validation.');

        // --- Added from old ValidPathTest ---
        $validator = Validator::make(['path' => 'valid/path/to/file.txt'], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'A valid relative path should pass.');

        $validator = Validator::make(['path' => '../invalid/path'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'Directory traversal with ../ should fail.');

        $validator = Validator::make(['path' => '..\\invalid\\path'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'Backslash directory traversal should fail.');

        $validator = Validator::make(['path' => 'invalid*path'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'Invalid character * should fail.');

        $validator = Validator::make(['path' => 'valid/path?with/invalid'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'Path with ? should fail.');

        $validator = Validator::make(['path' => '/'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'Root path should pass.');


        $validator = Validator::make(['path' => 'path with spaces'], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'Path with spaces should pass.');

        $validator = Validator::make(['path' => 'C:/path/to/file'], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'Windows path with colon should pass.');

        $validator = Validator::make(['path' => './current/directory'], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'Relative path with ./ should pass.');

        // --- Extra edge cases for more coverage ---
        $validator = Validator::make(['path' => '...'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'Path with only dots should fail.');

        $validator = Validator::make(['path' => "folder\u{200B}name"], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'Zero-width space in path should fail.');

        $validator = Validator::make(['path' => '/διαδρομή/έγγραφο.txt'], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'Greek path should pass.');

        $validator = Validator::make(['path' => '/путь/файл.txt'], ['path' => $rules]);
        $this->assertTrue($validator->passes(), 'Cyrillic path should pass.');

        $validator = Validator::make(['path' => '/../etc/passwd'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'Leading slash traversal should fail.');

        $validator = Validator::make(['path' => 'folder\\..\\evil'], ['path' => $rules]);
        $this->assertFalse($validator->passes(), 'Mixed slash traversal should fail.');
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

        $validator = Validator::make(
            ['fileList' => ['01F8MECHZX3TBDSZ7XRADM79XE', '01F8MECHZX3TBDSZ7XRADM79XF']],
            $rules
        );
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

        // Basic valid cases
        $validator = Validator::make(['itemName' => 'Valid Item Name 123'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'A valid item name should pass validation.');

        $validator = Validator::make(['itemName' => 'simple'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'A simple name should pass validation.');

        $validator = Validator::make(['itemName' => 'file_with_underscores'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Names with underscores should pass validation.');

        $validator = Validator::make(['itemName' => 'file-with-hyphens'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Names with hyphens should pass validation.');

        $validator = Validator::make(['itemName' => 'file.with.dots.txt'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Names with dots should pass validation.');

        $validator = Validator::make(['itemName' => 'Invalid|ItemName'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'An item name with invalid characters should fail validation.');


        // Greek character tests
        $validator = Validator::make(['itemName' => 'Αρχείο'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Greek characters should pass validation.');

        $validator = Validator::make(['itemName' => 'Έγγραφο Πελάτη'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Greek characters with spaces should pass validation.');

        $validator = Validator::make(['itemName' => 'Φάκελος_2023'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Mixed Greek characters with numbers and underscores should pass validation.');

        $validator = Validator::make(['itemName' => 'ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩ'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Greek uppercase characters should pass validation.');

        $validator = Validator::make(['itemName' => 'αβγδεζηθικλμνξοπρστυφχψω'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Greek lowercase characters should pass validation.');

        $validator = Validator::make(['itemName' => 'Άλφα Βήτα Γάμμα'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Greek characters with accents should pass validation.');

        // Other Unicode tests
        $validator = Validator::make(['itemName' => 'Файл'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Cyrillic characters should pass validation.');

        $validator = Validator::make(['itemName' => 'Documento español'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Spanish characters with accents should pass validation.');

        $validator = Validator::make(['itemName' => 'Café münü'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Mixed accented characters should pass validation.');

        // Length validation
        $validator = Validator::make(['itemName' => str_repeat('a', 255)], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'A 255-character name should pass validation.');

        $validator = Validator::make(['itemName' => str_repeat('a', 256)], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'An item name exceeding 255 characters should fail validation.');

        $validator = Validator::make(['itemName' => str_repeat('ώ', 255)], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'A 255-character Greek name should pass validation.');

        // Required field validation
        $validator = Validator::make(['itemName' => ''], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'An empty item name should fail validation.');

        $validator = Validator::make(['itemName' => null], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'A null item name should fail validation.');

        $validator = Validator::make([], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'A missing item name should fail validation.');

        // Whitespace tests
        $validator = Validator::make(['itemName' => '   '], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Spaces-only name should pass validation (if allowed by regex).');

        $validator = Validator::make(['itemName' => 'File with spaces'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Names with normal spaces should pass validation.');

        $validator = Validator::make(['itemName' => '  Leading and trailing spaces  '], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Names with leading/trailing spaces should pass validation.');

        // Security tests - Directory traversal attempts
        $validator = Validator::make(['itemName' => '../'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Directory traversal with ../ should fail validation.');

        $validator = Validator::make(['itemName' => '..\\'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Directory traversal with ..\\ should fail validation.');

        $validator = Validator::make(['itemName' => '/etc/passwd'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Absolute path should fail validation.');

        $validator = Validator::make(['itemName' => 'folder/../file'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Path with traversal should fail validation.');

        $validator = Validator::make(['itemName' => 'C:\\Windows\\System32'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Windows path should fail validation.');

        // Invalid file system characters
        $validator = Validator::make(['itemName' => 'file<name'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with < should fail validation.');

        $validator = Validator::make(['itemName' => 'file>name'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with > should fail validation.');

        $validator = Validator::make(['itemName' => 'file:name'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with : should fail validation.');

        $validator = Validator::make(['itemName' => 'file"name'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with " should fail validation.');

        $validator = Validator::make(['itemName' => 'file|name'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with | should fail validation.');

        $validator = Validator::make(['itemName' => 'file?name'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with ? should fail validation.');

        $validator = Validator::make(['itemName' => 'file*name'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with * should fail validation.');

        // Control characters
        $validator = Validator::make(['itemName' => "file\x00name"], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with null bytes should fail validation.');

        $validator = Validator::make(['itemName' => "file\x01name"], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with control characters should fail validation.');

        $validator = Validator::make(['itemName' => "file\x1fname"], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with ASCII control characters should fail validation.');

        $validator = Validator::make(['itemName' => "file\x7fname"], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with DEL character should fail validation.');

        $validator = Validator::make(['itemName' => "file\nnewline"], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with newlines should fail validation.');

        $validator = Validator::make(['itemName' => "file\ttab"], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with tabs should fail validation.');

        // Edge cases with dots and spaces
        $validator = Validator::make(['itemName' => 'file.'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Names ending with dot should pass validation (if allowed).');

        $validator = Validator::make(['itemName' => '.hiddenfile'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Names starting with dot should pass validation.');

        $validator = Validator::make(['itemName' => ' '], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with only space should fail validation.');

        $validator = Validator::make(['itemName' => '...'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with multiple dots should fail validation.');

        // Unicode edge cases
        $validator = Validator::make(['itemName' => 'file\u200bname'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with zero-width space should fail validation.');

        $validator = Validator::make(['itemName' => 'file\u202ename'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Names with right-to-left override should fail validation.');

        // Very long Unicode names
        $validator = Validator::make(['itemName' => str_repeat('Αβ', 128)], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Very long Unicode names should fail validation.');

        // Mixed valid characters
        $validator = Validator::make(['itemName' => 'File_123-άλφα βήτα.txt'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Mixed valid characters with Greek should pass validation.');

        $validator = Validator::make(['itemName' => 'Αρχείο_2023-Final Version.pdf'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Real-world Greek filename should pass validation.');

        // Homograph attack tests (visually similar characters)
        $validator = Validator::make(['itemName' => 'аdmin.php'], ['itemName' => $rules]); // Cyrillic 'а'
        $this->assertTrue($validator->passes(), 'Cyrillic characters should pass (homograph detection not in regex).');

        // Numbers in different scripts
        $validator = Validator::make(['itemName' => 'file１２３'], ['itemName' => $rules]); // Fullwidth numbers
        $this->assertTrue($validator->passes(), 'Fullwidth numbers should pass validation.');

        // --- Additional Security Tests ---
        $validator = Validator::make(['itemName' => './file'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Relative path starting with ./ should fail validation.');

        $validator = Validator::make(['itemName' => '.\\file'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Relative path starting with .\\ should fail validation.');

        $validator = Validator::make(['itemName' => ' ' . str_repeat('a', 253) . ' '], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Name with leading and trailing spaces should pass if total length ≤ 255.');

        $validator = Validator::make(['itemName' => 'COM1'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Windows reserved device name COM1 is not blocked by regex and should pass.');

        $validator = Validator::make(['itemName' => 'aux'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Windows reserved device name aux is not blocked by regex and should pass.');

        // --- Edge Character Tests ---
        $validator = Validator::make(['itemName' => '.'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Single dot should fail validation.');

        $validator = Validator::make(['itemName' => '/'], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Single slash should fail validation.');

        $validator = Validator::make(['itemName' => ' '], ['itemName' => $rules]);
        $this->assertFalse($validator->passes(), 'Single space should fail validation.');

        $validator = Validator::make(['itemName' => 'file..name'], ['itemName' => $rules]);
        $this->assertTrue($validator->passes(), 'Multiple dots in middle should pass validation.');


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
