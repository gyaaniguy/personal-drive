<?php

namespace App\Models;

use App\Helpers\FileSizeFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SplFileInfo;

class LocalFile extends Model
{
    use HasUlids;

    protected $hidden = ['private_path', 'user_id'];

    protected $fillable = ['filename', 'is_dir', 'public_path', 'private_path', 'size', 'user_id', 'file_type'];

    public $timestamps = true;

    public function sharedFiles(): HasMany
    {
        return $this->hasMany(SharedFile::class, 'file_id');
    }

    public static function getById(string $id): ?self
    {
        return self::where('id', $id)->first();
    }
    public static function getByIds(array $fileIds): Builder
    {
        return self::whereIn('id', $fileIds);
    }

    public static function setHasThumbnail(array $fileIds): int
    {
        return self::getByIds($fileIds)->update(['has_thumbnail' => 1]);
    }

    public function getPublicPathname(): string
    {
        return $this->public_path.DIRECTORY_SEPARATOR.$this->filename;
    }

    public function getPrivatePathNameForFile(): string
    {
        return $this->private_path.DIRECTORY_SEPARATOR.$this->filename;
    }

    public static function insertRows(array $insertArr): int
    {
        return self::upsert($insertArr, ['filename', 'public_path']);
    }

    public static function clearTable(): void
    {
        self::truncate();
    }

    public static function getFilesForPublicPath(string $publicPath): Collection
    {
        $fileItems = self::where('public_path', $publicPath)
            ->orderBy('created_at', 'desc')
            ->get();

        return self::modifyFileCollectionForDrive($fileItems);
    }

    public static function modifyFileCollectionForGuest(Collection $fileItems, string $publicPath = ''): Collection
    {
        return $fileItems->map(function ($item) use ($publicPath) {
            $item->sizeText = self::getItemSizeText($item);
            if ($publicPath) {
                $item->public_path = ltrim(substr($item->public_path, strlen($publicPath)), '/');
            }

            return $item;
        });
    }

    public static function modifyFileCollectionForDrive(Collection $fileItems): Collection
    {
        return $fileItems->map(function ($item) {
            $item->sizeText = self::getItemSizeText($item);
            return $item;
        });
    }

    public static function searchFiles(string $searchQuery): Collection
    {
        $fileItems = static::where('filename', 'like', $searchQuery.'%')
            ->orWhere('filename', 'like', '%'.$searchQuery)
            ->get();

        return self::modifyFileCollectionForDrive($fileItems);
    }

    public function deleteUsingPublicPath()
    {
        return $this->where('public_path', 'like', $this->getPublicPathname().'%')->delete();
    }

    public static function getIdsByLikePublicPath(string $search): array
    {
        return self::where("public_path", "like", $search . "%")->pluck('id')->toArray();
    }


    public static function getItemSizeText($item): string
    {
        return $item->size || $item->is_dir ? FileSizeFormatter::format((int) $item->size) : '0 KB' ;
    }

    public static function getForFileObj(SplFileInfo $file)
    {
        return self::where('filename', $file->getFilename())
            ->where('public_path', $file->getRelativePath())
            ->first();

    }

    public function isValidFile(): bool
    {
        return is_file($this->getPrivatePathNameForFile()) && $this->is_dir === 0;
    }
    public function isValidDir(): bool
    {
        return is_dir($this->getPrivatePathNameForFile()) && $this->is_dir === 1;
    }
    public function fileExists(): bool
    {
        return file_exists($this->getPrivatePathNameForFile());
    }
}
