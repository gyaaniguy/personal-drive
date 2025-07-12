<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_can_be_created_using_factory()
    {
        $setting = Setting::factory()->create();
        $this->assertNotNull($setting->id);
        $this->assertDatabaseHas('settings', ['id' => $setting->id]);
    }

    public function test_update_setting_creates_new_setting()
    {
        $key = 'test_key';
        $value = 'test_value';
        $result = Setting::updateSetting($key, $value);

        $this->assertTrue($result);
        $this->assertDatabaseHas('settings', [
            'key' => $key,
            'value' => $value,
        ]);
    }

    public function test_update_setting_updates_existing_setting()
    {
        $key = 'existing_key';
        $oldValue = 'old_value';
        $newValue = 'new_value';

        Setting::create(['key' => $key, 'value' => $oldValue]);
        $result = Setting::updateSetting($key, $newValue);

        $this->assertTrue($result);
        $this->assertDatabaseHas('settings', [
            'key' => $key,
            'value' => $newValue,
        ]);
        $this->assertDatabaseMissing('settings', [
            'key' => $key,
            'value' => $oldValue,
        ]);
    }

    public function test_get_setting_by_key_name_returns_correct_value()
    {
        $key = 'another_key';
        $value = 'another_value';
        Setting::create(['key' => $key, 'value' => $value]);

        $retrievedValue = Setting::getSettingByKeyName($key);
        $this->assertEquals($value, $retrievedValue);
    }

    public function test_get_setting_by_key_name_returns_empty_string_for_non_existent_key()
    {
        $retrievedValue = Setting::getSettingByKeyName('non_existent_key');
        $this->assertEquals('', $retrievedValue);
    }

    public function test_setting_attributes_are_fillable()
    {
        $settingData = [
            'key' => 'fillable_key',
            'value' => 'fillable_value',
        ];
        $setting = Setting::create($settingData);

        $this->assertEquals('fillable_key', $setting->key);
        $this->assertEquals('fillable_value', $setting->value);
    }

    public function test_storage_path_static_property_is_correct()
    {
        $this->assertEquals('storage_path', Setting::$storagePath);
    }
}
