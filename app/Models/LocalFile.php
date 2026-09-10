<?php

namespace App\Models;

use App\Helpers\FileSizeFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Finder\SplFileInfo;

class LocalFile extends Model
{
    use HasFactory;
    use HasUlids;

    public $timestamps = true;
    protected $hidden = ['private_path', 'user_id', 'laravel_through_key'];
    protected $fillable = ['filename', 'is_dir', 'public_path', 'private_path', 'size', 'user_id', 'file_type'];

    public static function getByName(string $name): ?self
    {
        return self::where('filename', $name)->first();
    }

    public static function getById(string $id): ?self
    {
        return self::where('id', $id)->first();
    }

    public static function setHasThumbnail(array $fileIds): int
    {
        return self::getByIds($fileIds)->update(['has_thumbnail' => 1]);
    }

    public static function getByIds(array $fileIds): Builder
    {
        return self::whereIn('id', $fileIds);
    }

    public static function getByIdsForUser(array $fileIds): Builder
    {
        return self::where('user_id', auth()->user()->id)->whereIn('id', $fileIds);
    }

    public static function getByPathAndName(string $publicPath, string $filename): ?self
    {
        return self::where('filename', $filename)
            ->where('public_path', $publicPath)
            ->first();
    }

    public static function insertRows(array $insertArr): int
    {
        return self::upsert($insertArr, ['filename', 'public_path']);
    }

    public static function clearTable(): void
    {
        self::truncate();
    }

    public static function keyedByPath(): Collection
    {
        return self::get(['id', 'public_path', 'filename'])
            ->keyBy(fn ($f) => $f->public_path . "\0" . $f->filename);
    }

    public static function getFilesForPublicPath(string $publicPath): Builder
    {
        return self::where('public_path', $publicPath)
            ->orderBy('filename', 'desc');
    }

    public static function modifyFileCollectionForDrive(Collection $fileItems): Collection
    {
        return $fileItems->filter(
            fn ($item) => file_exists($item->getPrivatePathNameForFile())
        )->map(
            function ($item) {
                $item->sizeText = self::getItemSizeText($item);
                $item->date = filemtime($item->getPrivatePathNameForFile());
                return $item;
            }
        )->values();
    }

    public static function getItemSizeText($item): string
    {
        return $item->size || $item->is_dir ? FileSizeFormatter::format((int) $item->size) : '0 KB';
    }

    public static function modifyFileCollectionForGuest(Collection $fileItems, string $publicPath = ''): Collection
    {
        return $fileItems->filter(
            fn ($item) => file_exists($item->getPrivatePathNameForFile())
        )->map(
            function ($item) use ($publicPath) {
                $item->sizeText = self::getItemSizeText($item);
                $item->date = filemtime($item->getPrivatePathNameForFile());
                if ($publicPath) {
                    $item->public_path = substr($item->getPublicPath(), strlen($publicPath));
                }

                return $item;
            }
        )->values();
    }

    public function getPublicPath(): string
    {
        return $this->public_path ? $this->public_path . DS : '';
    }

    public static function searchFiles(string $searchQuery): Builder
    {
        return static::where('filename', 'like', '%' . $searchQuery . '%')
            ->where('user_id', auth()->user()->id);
    }

    public static function getIdsByLikePublicPath(string $search): array
    {
        return self::getByPublicPathLikeSearch($search)->pluck('id')->toArray();
    }

    public static function getByPublicPathLikeSearch(string $search): Builder
    {
        return self::where(
            function ($query) use ($search) {
                $query->where('public_path', $search)
                    ->orWhereRaw('instr(public_path, ?) = 1', [$search . DS]);
            }
        );
    }

    public static function getForFileObj(SplFileInfo $file)
    {
        return self::where('filename', $file->getFilename())
            ->where('public_path', $file->getRelativePath())
            ->first();
    }

    public function sharedFiles(): HasMany
    {
        return $this->hasMany(SharedFile::class, 'file_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'local_file_id');
    }

    public function deleteUsingPublicPath()
    {
        return self::getByPublicPathLikeSearch($this->getPublicPathPlusName())->delete();
    }

    public function getPublicPathPlusName(string $customFileName = '', ?string $customPath = null): string
    {
        if ($customPath === null) {
            $path = $this->getPublicPath();
        } elseif ($customPath === '') {
            $path = '';
        } else {
            $path = $customPath . DS;
        }
        return $path . ($customFileName ?: $this->filename);
    }

    public function getFullPathFromContentRoot(string $customFileName = '', ?string $customPath = null): string
    {
        return CONTENT_SUBDIR . DS . $this->getPublicPathPlusName($customFileName, $customPath);
    }

    public function isValidFile(): bool
    {
        return is_file($this->getPrivatePathNameForFile()) && !$this->is_dir;
    }

    public function getPrivatePathNameForFile(): string
    {
        return $this->private_path . DS . $this->filename;
    }

    public function isValidDir(): bool
    {
        return is_dir($this->getPrivatePathNameForFile()) && $this->is_dir;
    }

    public function isInvalid(): bool
    {
        return !$this->isValidFile() && !$this->isValidDir();
    }

}
