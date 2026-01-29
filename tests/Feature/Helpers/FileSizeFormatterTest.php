<?php

use App\Helpers\FileSizeFormatter;

it(
    'formats bytes less than one KB', function () {
        expect(FileSizeFormatter::format(512))->toBe('1 KB')
            ->and(FileSizeFormatter::format(100))->toBe('1 KB');
    }
);
it(
    'formats bytes 0', function () {
        expect(FileSizeFormatter::format(0))->toBe('')
            ->and(FileSizeFormatter::format(100))->toBe('1 KB');
    }
);

it(
    'formats bytes in KB', function () {
        expect(FileSizeFormatter::format(1024))->toBe('1 KB')
            ->and(FileSizeFormatter::format(2048))->toBe('2 KB')
            ->and(FileSizeFormatter::format(5148))->toBe('5 KB');
    }
);

it(
    'formats bytes in MB', function () {
        expect(FileSizeFormatter::format(1048576))->toBe('1 MB')
            ->and(FileSizeFormatter::format(2048000))->toBe('1.95 MB')
            ->and(FileSizeFormatter::format(9048000))->toBe('8.63 MB');
    }
);

it(
    'formats bytes in GB', function () {
        expect(FileSizeFormatter::format(1073741824))->toBe('1 GB')
            ->and(FileSizeFormatter::format(2000000000))->toBe('1.86 GB');
    }
);
