<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    public static string $storagePath = 'storage_path';

    protected $fillable = ['key', 'value'];

    public static function updateSetting(string $key, string $value): bool
    {
        $result = Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        return $result->wasRecentlyCreated || $result->wasChanged() || $result->exists;
    }

    public static function getSettingByKeyName(string $key): string
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : '';
    }
}
