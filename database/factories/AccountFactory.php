<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => $this->faker->company().' Wallet',
            'provider' => 'subadq_a',
            'settings' => [
                'document' => $this->faker->numerify('###########'),
            ],
            'webhook_url' => 'https://example.test/webhooks',
            'webhook_secret' => Str::random(20),
            'active' => true,
        ];
    }

    public function subadqB(): self
    {
        return $this->state(fn () => [
            'provider' => 'subadq_b',
        ]);
    }
}
