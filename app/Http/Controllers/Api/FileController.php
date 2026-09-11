<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateFileRequest;
use App\Http\Requests\Api\ListFilesRequest;
use App\Http\Requests\Api\MoveFilesRequest;
use App\Http\Requests\Api\RenameFileRequest;
use App\Http\Requests\Api\SaveFileRequest;
use App\Http\Requests\Api\UploadFilesRequest;
use App\Models\LocalFile;
use App\Services\FileDeleteService;
use App\Services\FileMoveService;
use App\Services\FileRenameService;
use App\Services\FileSaveService;
use App\Services\LocalFileStatsService;
use App\Services\PathService;
use App\Helpers\ResponseHelper;
use App\Services\UploadService;
use App\Traits\FlashMessages;
use App\Traits\HasJsonPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Exception;

class FileController extends Controller
{
    use HasJsonPagination;
    use FlashMessages;

    protected PathService $pathService;
    protected LocalFileStatsService $localFileStatsService;
    protected UploadService $uploadService;
    protected FileDeleteService $fileDeleteService;
    protected FileMoveService $fileMoveService;
    protected FileRenameService $fileRenameService;
    protected FileSaveService $fileSaveService;

    public function __construct(
        PathService $pathService,
        LocalFileStatsService $localFileStatsService,
        UploadService $uploadService,
        FileDeleteService $fileDeleteService,
        FileMoveService $fileMoveService,
        FileRenameService $fileRenameService,
        FileSaveService $fileSaveService,
    ) {
        $this->pathService = $pathService;
        $this->localFileStatsService = $localFileStatsService;
        $this->uploadService = $uploadService;
        $this->fileDeleteService = $fileDeleteService;
        $this->fileMoveService = $fileMoveService;
        $this->fileRenameService = $fileRenameService;
        $this->fileSaveService = $fileSaveService;
    }

    public function index(ListFilesRequest $request): JsonResponse
    {
        $path = $request->validated('path') ?? '';
        $rawPath = $path;
        $path = $this->pathService->cleanDrivePublicPath($path);
        $pathEcho = $rawPath !== '' && ltrim($rawPath, '/') === $path ? $rawPath : $path;

        $perPage = $request->validated('per_page', 50);

        // Disk is truth, the index is a cache. Rows whose file is gone must be
        // dropped BEFORE paginating: filtering after a DB slice leaves `total`
        // and `last_page` describing phantom rows and can hand back an empty
        // page while later pages still hold files.
        // ponytail: loads the whole folder to filter it (~15ms per 5k rows, measured).
        // If that ever bites, reconcile the path against disk once and page in SQL.
        $files = LocalFile::filesForDrive($path);

        $currentPage = LengthAwarePaginator::resolveCurrentPage();

        $paginator = new LengthAwarePaginator(
            $files->forPage($currentPage, $perPage)->values(),
            $files->count(),
            $perPage,
            $currentPage
        );

        return $this->paginateJson($paginator, 'files', ['path' => $pathEcho]);
    }

    public function show(string $id): JsonResponse
    {
        $file = LocalFile::getById($id);

        if (!$file || !file_exists($file->getPrivatePathNameForFile())) {
            return ResponseHelper::json('File not found', false, 404);
        }

        return response()->json(['file' => array_merge($file->toArray(), [
            'sizeText' => LocalFile::getItemSizeText($file),
            'date' => filemtime($file->getPrivatePathNameForFile()),
        ])]);
    }

    public function upload(UploadFilesRequest $request): JsonResponse
    {
        $files = $request->validated('files') ?? [];
        $publicPath = $request->validated('path') ?? '';
        $publicPath = $this->pathService->cleanDrivePublicPath($publicPath);
        $privatePath = $this->pathService->genPrivatePathFromPublic($publicPath);

        if (!$files) {
            return ResponseHelper::json('No files uploaded', false, 422);
        }
        if (!$privatePath) {
            return ResponseHelper::json('Could not find storage path', false, 422);
        }

        $result = $this->uploadService->processFileUpload($files, $privatePath, $publicPath, swallowErrors: true);

        $this->localFileStatsService->generateStats($publicPath, $files);

        $message = $result['successful'] > 0
            ? 'Files uploaded: ' . $result['successful'] . ' out of ' . count($files)
            : 'Some/All files upload failed';

        if ($result['conflicts']) {
            $message .= ' (Conflicts: ' . $this->uploadService->summarizeConflicts($result['conflicts']) . ')';
        }

        $newFiles = LocalFile::filesForDrive($publicPath);

        return response()->json([
            'message' => $message,
            'files' => $newFiles->values(),
        ]);
    }

    public function create(CreateFileRequest $request): JsonResponse
    {
        $name = $request->validated('name');
        $type = $request->validated('type');
        $publicPath = $request->validated('path') ?? '';
        $publicPath = $this->pathService->cleanDrivePublicPath($publicPath);

        $isFile = $type === 'file';
        $result = $this->fileSaveService->createItem($name, $publicPath, $isFile);

        if (!$result['success']) {
            return ResponseHelper::json($result['message'], false, 422);
        }

        $file = LocalFile::getByPathAndName($publicPath, $name);

        return response()->json([
            'message' => ucfirst($type) . ' created',
            'file' => $file,
        ]);
    }

    public function download(string $id)
    {
        $file = LocalFile::getById($id);

        if (!$file) {
            return ResponseHelper::json('File not found', false, 404);
        }

        $privatePath = $file->getPrivatePathNameForFile();

        if (!is_file($privatePath)) {
            return ResponseHelper::json('File not found on disk', false, 404);
        }

        $mimeType = mime_content_type($privatePath) ?: 'application/octet-stream';

        return response()->streamDownload(
            function () use ($privatePath) {
                readfile($privatePath);
            },
            $file->filename,
            ['Content-Type' => $mimeType]
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $file = LocalFile::getById($id);

        if (!$file) {
            return ResponseHelper::json('File not found', false, 404);
        }

        $rootPath = $this->pathService->getStorageFolderPath();

        $result = $this->fileDeleteService->deleteFiles(LocalFile::getByIds([$id]), $rootPath);

        if (!$result['deleted']) {
            return ResponseHelper::json($this->buildDelFailureMessage($result), false, 500);
        }

        $message = "Deleted " . count($result['deleted']) . " files";
        if (count($result['unreadable']) || count($result['readonly'])) {
            $message .= '. ' . $this->buildDelFailureMessage($result);
        }

        return ResponseHelper::json($message);
    }

    public function move(MoveFilesRequest $request): JsonResponse
    {
        $fileIds = $request->validated('fileList');
        $destination = $request->validated('destination');
        if ($destination === '/') {
            $destination = '';
        }

        $this->fileMoveService->moveFiles($fileIds, $destination);

        $newFiles = LocalFile::filesForDrive(
            $this->pathService->cleanDrivePublicPath($destination)
        );
        return response()->json([
            'message' => 'Files moved',
            'files' => $newFiles->values(),
        ]);
    }

    public function rename(RenameFileRequest $request, string $id): JsonResponse
    {
        $name = $request->validated('name');
        $file = LocalFile::getById($id);

        if (!$file) {
            return ResponseHelper::json('File not found', false, 404);
        }

        try {
            $this->fileRenameService->renameFile($file, $name);
        } catch (Exception $e) {
            Log::error('File rename failed', ['exception' => $e]);
            return ResponseHelper::json('Rename failed', false, 422);
        }

        $file->refresh();

        return response()->json([
            'message' => 'File renamed',
            'file' => array_merge($file->toArray(), [
                'sizeText' => LocalFile::getItemSizeText($file),
                'date' => filemtime($file->getPrivatePathNameForFile()),
            ]),
        ]);
    }

    public function save(SaveFileRequest $request, string $id): JsonResponse
    {
        $result = $this->fileSaveService->save($id, $request->validated('content'));

        if ($result['message'] === 'Could not find file') {
            return ResponseHelper::json($result['message'], false, 404);
        }

        if (!$result['success']) {
            return ResponseHelper::json($result['message'], false, 422);
        }

        return response()->json([
            'message' => 'File saved',
            'file' => $result['file']->fresh(),
        ]);
    }
}
