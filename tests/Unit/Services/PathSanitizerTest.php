<?php

namespace Tests\Unit\Services;

use App\Services\PathService;
use PHPUnit\Framework\TestCase;

class PathSanitizerTest extends TestCase
{
    protected PathService $pathService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pathService = new PathService();
    }

    // ============================================================
    // sanitizeUploadPath — getClientOriginalPath() = path + filename
    // ============================================================

    public function test_simple_filename_passes(): void
    {
        $this->assertSame('file.txt', $this->pathService->sanitizeUploadPath('file.txt'));
    }

    public function test_nested_folder_path_passes(): void
    {
        $this->assertSame('folder' . DIRECTORY_SEPARATOR . 'sub' . DIRECTORY_SEPARATOR . 'file.txt', $this->pathService->sanitizeUploadPath('folder/sub/file.txt'));
    }

    public function test_file_with_spaces_and_unicode_passes(): void
    {
        $this->assertSame('my folder' . DIRECTORY_SEPARATOR . '文件.pdf', $this->pathService->sanitizeUploadPath('my folder/文件.pdf'));
    }

    // Traversal stripped, legitimate segments preserved

    public function test_parent_dir_traversal_stripped(): void
    {
        $this->assertSame('etc' . DIRECTORY_SEPARATOR . 'crontab', $this->pathService->sanitizeUploadPath('../../etc/crontab'));
    }

    public function test_mixed_traversal_segments_stripped(): void
    {
        $this->assertSame('folder' . DIRECTORY_SEPARATOR . 'file.txt', $this->pathService->sanitizeUploadPath('folder/../file.txt'));
    }

    public function test_only_traversal_returns_empty(): void
    {
        $this->assertSame('', $this->pathService->sanitizeUploadPath('../..'));
    }

    public function test_backslash_traversal_stripped(): void
    {
        $this->assertSame('etc' . DIRECTORY_SEPARATOR . 'hosts', $this->pathService->sanitizeUploadPath('..\\..\\etc\\hosts'));
    }

    // Dangerous input rejected

    public function test_null_byte_rejected(): void
    {
        $this->assertSame('', $this->pathService->sanitizeUploadPath("file\x00.txt"));
    }

    public function test_empty_string_returns_empty(): void
    {
        $this->assertSame('', $this->pathService->sanitizeUploadPath(''));
    }

    public function test_hidden_file_and_single_dot_preserved(): void
    {
        $this->assertSame('.htaccess', $this->pathService->sanitizeUploadPath('.htaccess'));
        $this->assertSame('config' . DIRECTORY_SEPARATOR . '.env', $this->pathService->sanitizeUploadPath('config/.env'));
    }

    public function test_leading_trailing_double_slashes_normalized(): void
    {
        $this->assertSame('folder' . DIRECTORY_SEPARATOR . 'file.txt', $this->pathService->sanitizeUploadPath('/folder//file.txt/'));
    }

    // ============================================================
    // sanitizeFileName — getClientOriginalName() = just filename
    // ============================================================

    public function test_valid_filename_passes(): void
    {
        $this->assertSame('document.pdf', $this->pathService->sanitizeFileName('document.pdf'));
        $this->assertSame('my file.txt', $this->pathService->sanitizeFileName('my file.txt'));
        $this->assertSame('文件.docx', $this->pathService->sanitizeFileName('文件.docx'));
        $this->assertSame('.env', $this->pathService->sanitizeFileName('.env'));
    }

    public function test_filename_strips_path_components(): void
    {
        $this->assertSame('file.txt', $this->pathService->sanitizeFileName('folder/file.txt'));
        $this->assertSame('file.txt', $this->pathService->sanitizeFileName('folder\\file.txt'));
        $this->assertSame('file.txt', $this->pathService->sanitizeFileName('../../file.txt'));
    }

    public function test_filename_rejects_dangerous_chars(): void
    {
        // Control chars
        $this->assertSame('', $this->pathService->sanitizeFileName("file\x00.txt"));
        $this->assertSame('', $this->pathService->sanitizeFileName("file\x01.txt"));
        // Filesystem dangerous chars
        $this->assertSame('', $this->pathService->sanitizeFileName('file<tag>.txt'));
        $this->assertSame('', $this->pathService->sanitizeFileName('file|name.txt'));
        $this->assertSame('', $this->pathService->sanitizeFileName('file*name.txt'));
        // Zero-width chars
        $this->assertSame('', $this->pathService->sanitizeFileName("file\u{200B}name.txt"));
    }

    public function test_filename_rejects_dots_or_spaces_only(): void
    {
        $this->assertSame('', $this->pathService->sanitizeFileName('...'));
        $this->assertSame('', $this->pathService->sanitizeFileName('   '));
        $this->assertSame('', $this->pathService->sanitizeFileName(' . . '));
    }

    public function test_filename_empty_returns_empty(): void
    {
        $this->assertSame('', $this->pathService->sanitizeFileName(''));
    }
}
