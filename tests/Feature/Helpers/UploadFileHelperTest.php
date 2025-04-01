<?php

use App\Helpers\UploadFileHelper;

beforeEach(function () {
    // Setup code if needed
});

it('returns the full path of the uploaded file', function () {
    $_FILES['files']['full_path'][0] = '/path/to/file.txt';
    $fullPath = UploadFileHelper::getUploadedFileFullPath(0);
    expect($fullPath)->toBe('/path/to/file.txt');
});

it('returns the realtive path', function () {
    $_FILES['files']['full_path'][0] = './file.txt';
    $fullPath = UploadFileHelper::getUploadedFileFullPath(0);
    expect($fullPath)->toBe('/file.txt');
});

it('returns the realtive path 2', function () {
    $_FILES['files']['full_path'][0] = '/file.txt';
    $fullPath = UploadFileHelper::getUploadedFileFullPath(0);
    expect($fullPath)->toBe('/file.txt');
});

it('creates a folder with the specified permissions', function () {
    $path = __DIR__ . '/test_folder';
    $result = UploadFileHelper::makeFolder($path, 0750);
    expect($result)->toBeTrue()
        ->and(is_dir($path))->toBeTrue()
        ->and(decoct(fileperms($path) & 0777))->toBe('750');
    rmdir($path); // Clean up
});

it('returns true if the folder already exists', function () {
    $path = __DIR__;
    $result = UploadFileHelper::makeFolder($path);
    expect($result)->toBeTrue();
});
