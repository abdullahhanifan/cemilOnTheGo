<?php

namespace Database\Factories;

use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'city' => fake()->randomElement(['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Denpasar']),
            'area' => fake()->randomElement(['Grand Indonesia', 'Paris Van Java', 'Pasar Baru', 'Malioboro Mall', 'Pasar Beringharjo']),
            'address' => fake()->address(),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'opening_hours' => [
                ['day_from' => 1, 'day_to' => 7, 'opens_at' => '10:00', 'closes_at' => '22:00'],
            ],
            'notes' => null,
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
