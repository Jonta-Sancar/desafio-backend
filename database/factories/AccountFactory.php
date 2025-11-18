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
                'merchant_id' => 'm123',
                'seller_id' => 'm123',
                'bank_account' => [
                    'bank_code' => '001',
                    'agencia' => '1234',
                    'conta' => '00012345',
                    'type' => 'checking',
                ],
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
