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
            'settings' => ['document' => '12345678900'],
            'webhook_url' => 'https://example.com/webhooks',
            'webhook_secret' => 'secret-key',
        ]);
    }
}
