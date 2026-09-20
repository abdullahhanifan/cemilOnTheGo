<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $name = fake()->unique()->name();

        return [
            'name' => $name,
            'phone' => '08'.fake()->numerify('##########'),
            'city' => fake()->randomElement(['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Denpasar']),
            'area' => fake()->randomElement(['Kemang', 'Dago', 'Tunjungan', 'Malioboro', 'Sanur']),
            'payment_method' => 'bank',
            'payment_provider' => null,
            'account_name' => $name,
            'account_number' => fake()->numerify('##########'),
            'notes' => null,
            'status' => 'active',
            'user_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    /**
     * A partner whose payment details are not filled in yet.
     */
    public function withoutPayment(): static
    {
        return $this->state(fn () => [
            'payment_method' => null,
            'payment_provider' => null,
            'account_name' => null,
            'account_number' => null,
        ]);
    }

    public function withProvider(string $provider = 'BCA'): static
    {
        return $this->state(fn () => ['payment_provider' => $provider]);
    }

    public function eWallet(): static
    {
        return $this->state(fn () => ['payment_method' => 'e_wallet']);
    }
}
