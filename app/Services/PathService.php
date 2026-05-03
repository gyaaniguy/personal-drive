<?php

namespace App\Services;

use App\Http\Requests\CommonRequest;
use App\Models\Setting;

class PathService
{
    public function getPlusContentRoot(string $publicPath, string $fileName = ''): string
    {
        return CONTENT_SUBDIR . DS . ($publicPath ? $publicPath . DS : '') . ( $fileName ?: '');
    }

    public function getThumbnailAbsPath(): string
    {
        return Setting::getStoragePath() . DS . THUMBS_SUBDIR;
    }

    public function genPrivatePathFromPublic(string $publicPath = ''): string
    {
        $privateRoot = $this->getStorageFolderPath();

        if (!$privateRoot) {
            return '';
        }

        if ($publicPath === '') {
            return $privateRoot . DS;
        }
        $publicPath = $this->cleanDrivePublicPath($publicPath);
        return $privateRoot . DS . $publicPath . DS;
    }

    public function getStorageFolderPath(): string
    {
        return Setting::getStoragePath() . DS . CONTENT_SUBDIR;
    }

    public function cleanDrivePublicPath(string $path): string
    {

        if ($path === '/drive') {
            return '';
        }
        if (str_starts_with($path, '/drive/')) {
            return substr($path, 7); // remove "/drive/"
        }
        return $path;
    }

    /**
     * Sanitize a client upload path (folder/subfolder/file.txt).
     * Removes traversal segments (..) while preserving legitimate path structure.
     * e.g. "folder/sub/file.txt" → "folder/sub/file.txt"
     * e.g. "../../etc/crontab" → "etc/crontab"
     */
    public function sanitizeUploadPath(string $path): string
    {
        if (str_contains($path, "\0")) {
            return '';
        }

        $segments = preg_split('#[/\\\\]+#', $path);
        $safe = array_filter($segments, fn($s) => $s !== '' && $s !== '..');

        return implode(DIRECTORY_SEPARATOR, $safe);
    }

    /**
     * Sanitize a single filename using the same rules as CommonRequest::baseNameRule().
     * Blocks: control chars, null bytes, traversal, dangerous filesystem chars.
     * Returns empty string if the name is unsafe.
     */
    public function sanitizeFileName(string $name): string
    {
        // Strip any path components (handles both / and \ separators)
        $name = str_replace('\\', DIRECTORY_SEPARATOR, $name);
        $name = basename($name);

        // Null byte
        if (str_contains($name, "\0")) {
            return '';
        }

        if (!preg_match(CommonRequest::SAFE_CHARS_REGEX, $name)) {
            return '';
        }

        if (preg_match(CommonRequest::DOTS_OR_SPACES_REGEX, $name)) {
            return '';
        }

        if (preg_match(CommonRequest::TRAVERSAL_REGEX, $name)) {
            return '';
        }

        return $name;
    }
}
