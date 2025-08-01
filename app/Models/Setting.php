<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    public static string $storagePath = 'storage_path';
    public static string $oldStoragePath = '';

    protected $fillable = ['key', 'value'];

    public static function revertStoragePath(): bool
    {
        if (Setting::$oldStoragePath) {
            return Setting::updateSetting('storage_path', Setting::$oldStoragePath);
        }
        return false;
    }

    public static function updateSetting(string $key, string $value): bool
    {
        Setting::$oldStoragePath = Setting::getStoragePath();
        $result = Setting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        return $result->wasRecentlyCreated || $result->wasChanged() || $result->exists;
    }

    public static function getStoragePath(): string
    {
        return Setting::getSettingByKeyName(Setting::$storagePath);
    }

    public static function getSettingByKeyName(string $key): string
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : '';
    }
}
