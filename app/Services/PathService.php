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
    public function getRootPathLen(): int
    {
        return strlen($this->getStorageFolderPath()) + 1;
    }

    /**
     * Verify that an absolute target path resolves inside the configured storage root.
     *
     * Resolves the deepest existing ancestor of $absoluteTargetPath via realpath
     * (walking up parent directories while the path does not exist) and returns
     * true only when that resolved ancestor is the storage root itself or a
     * descendant of it. This defeats symlinks that live inside the storage tree
     * but point outside of it.
     *
     * Returns false when the storage root cannot be resolved, or when the target
     * path resolves to a location outside (or above) the storage root.
     */
    public function isWithinStorageRoot(string $absoluteTargetPath): bool
    {
        $root = realpath(Setting::getStoragePath());
        if ($root === false) {
            return false;
        }

        $existingAncestor = $absoluteTargetPath;
        while (!file_exists($existingAncestor)) {
            $parent = dirname($existingAncestor);
            if ($parent === $existingAncestor || $parent === '.') {
                return false;
            }
            $existingAncestor = $parent;
        }

        $resolvedAncestor = realpath($existingAncestor);
        if ($resolvedAncestor === false) {
            return false;
        }

        return $resolvedAncestor === $root
            || str_starts_with($resolvedAncestor, rtrim($root, DS) . DS);
    }

    public function cleanDrivePublicPath(string $path): string
    {
        if ($path === '/drive') {
            return '';
        }

        if (str_starts_with($path, '/drive/')) {
            $path = substr($path, 7);
        }

        return rtrim($path, '/');
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
