<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'contact_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
            'category' => fake()->randomElement(['Jasa', 'Barang', 'Konsultan', 'Kontraktor']),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }
}
