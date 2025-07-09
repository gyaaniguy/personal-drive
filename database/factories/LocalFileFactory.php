<?php

namespace Database\Factories;

use App\Models\LocalFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class LocalFileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = LocalFile::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'filename' => $this->faker->word() . '.' . $this->faker->fileExtension(),
            'is_dir' => $this->faker->boolean(10), // 10% chance of being a directory
            'public_path' => '/' . $this->faker->word(),
            'private_path' => '/' . $this->faker->word(),
            'size' => $this->faker->numberBetween(100, 1000000),
            'user_id' => User::factory(),
            'file_type' => $this->faker->randomElement(['image', 'video', 'text', 'pdf', 'other']),
            'has_thumbnail' => $this->faker->boolean(30), // 30% chance of having a thumbnail
        ];
    }
}
