<?php

namespace Database\Factories;

use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use Illuminate\Database\Eloquent\Factories\Factory;

class SharedFileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SharedFile::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'share_id' => Share::factory(),
            'file_id' => LocalFile::factory(),
        ];
    }
}
