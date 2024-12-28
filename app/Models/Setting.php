<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getSettingByKeyName(string $key): string
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : '';
    }


}
