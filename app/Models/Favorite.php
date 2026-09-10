<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Favorite extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = ['user_id', 'local_file_id', 'favorited_at'];

    protected $hidden = ['user_id', 'local_file_id'];

    protected function casts(): array
    {
        return [
            'favorited_at' => 'datetime',
        ];
    }

    public static function listForCurrentUser(): Builder
    {
        return static::with('localFile:id,filename,public_path,is_dir')
            ->where('user_id', auth()->user()->id)
            ->orderByDesc('favorited_at')
            ->orderByDesc('id');
    }

    public static function snapshotPaths(): Collection
    {
        return static::query()
            ->join('local_files', 'favorites.local_file_id', '=', 'local_files.id')
            ->get(['favorites.user_id', 'favorites.favorited_at', 'local_files.public_path', 'local_files.filename']);
    }

    public static function restorePaths(Collection $snapshot): void
    {
        $files = LocalFile::keyedByPath();
        foreach ($snapshot as $fav) {
            $file = $files->get($fav->public_path . "\0" . $fav->filename);
            if ($file) {
                static::create([
                    'user_id' => $fav->user_id,
                    'local_file_id' => $file->id,
                    'favorited_at' => $fav->favorited_at,
                ]);
            }
        }
    }

    public static function removeForUser(string $id): bool
    {
        return static::where('id', $id)
            ->where('user_id', auth()->user()->id)
            ->delete() > 0;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function localFile(): BelongsTo
    {
        return $this->belongsTo(LocalFile::class);
    }
}
