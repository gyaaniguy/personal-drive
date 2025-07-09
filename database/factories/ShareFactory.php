<?php

namespace Database\Factories;

use App\Models\Share;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ShareFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Share::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'slug' => Str::random(10),
            'password' => $this->faker->boolean(50) ? bcrypt('password') : null, // 50% chance of having a password
            'expiry' => $this->faker->boolean(70) ? $this->faker->numberBetween(1, 30) : null, // 70% chance of having an expiry
            'public_path' => '/' . $this->faker->word(),
            'accessed_count' => $this->faker->numberBetween(0, 100),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'created_at' => Carbon::now()->subDays(100),
                'expiry' => 1,
            ];
        });
    }

    public function unexpired()
    {
        return $this->state(function (array $attributes) {
            return [
                'created_at' => Carbon::now(),
                'expiry' => 7,
            ];
        });
    }
}
