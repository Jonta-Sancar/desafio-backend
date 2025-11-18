<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $user->accounts()->create([
            'label' => 'Conta SubadqA',
            'provider' => 'subadq_a',
            'settings' => [
                'document' => '12345678900',
                'merchant_id' => 'm123',
                'seller_id' => 'm123',
                'bank_account' => [
                    'bank_code' => '001',
                    'agencia' => '1234',
                    'conta' => '00012345',
                    'type' => 'checking',
                ],
            ],
            'webhook_url' => 'https://example.com/webhooks',
            'webhook_secret' => 'secret-key',
        ]);

        $user->accounts()->create([
            'label' => 'Conta SubadqB',
            'provider' => 'subadq_b',
            'settings' => [
                'document' => '98765432100',
                'merchant_id' => 'm456',
                'seller_id' => 'm456',
                'bank_account' => [
                    'bank_code' => '237',
                    'agencia' => '0001',
                    'conta' => '9876543',
                    'type' => 'checking',
                ],
            ],
            'webhook_url' => 'https://example.com/webhooks-b',
            'webhook_secret' => 'secret-key-b',
        ]);
    }
}
