<?php

namespace App\Helpers;

class FileSizeFormatter
{
    public static function format(int $bytes): string
    {
        if ($bytes < 1024) {
            return '1 KB';
        }

        $units = ['KB', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes >= 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, ($i < 2 ? 0 : 2)) . ' ' . $units[$i];
    }
}
