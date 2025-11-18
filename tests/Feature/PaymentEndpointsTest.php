<?php

namespace Tests\Feature;

use App\Jobs\SimulateWebhookJob;
use App\Models\PixPayment;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PaymentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pix_endpoint_creates_record_and_dispatches_webhook_job(): void
    {
        Bus::fake();
        $user = User::factory()->create();

        $payload = [
            'amount' => 150.75,
            'user_id' => $user->id,
            'subadq' => 'subadqA',
        ];

        $response = $this->postJson('/api/pix', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'user_id' => $user->id,
            ]);

        $pix = PixPayment::first();
        $this->assertNotNull($pix);
        $this->assertSame('PENDING', $pix->status);
        $this->assertSame($user->id, $pix->user_id);

        Bus::assertDispatched(SimulateWebhookJob::class, function (SimulateWebhookJob $job) use ($pix) {
            return $job->type === 'pix' && $job->modelId === $pix->id;
        });
    }

    public function test_withdraw_endpoint_creates_record_and_dispatches_webhook_job(): void
    {
        Bus::fake();
        $user = User::factory()->create();

        $payload = [
            'amount' => 320.10,
            'user_id' => $user->id,
            'subadq' => 'subadqA',
        ];

        $response = $this->postJson('/api/withdraw', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'user_id' => $user->id,
            ]);

        $withdrawal = Withdrawal::first();
        $this->assertNotNull($withdrawal);
        $this->assertSame('PENDING', $withdrawal->status);
        $this->assertSame($user->id, $withdrawal->user_id);

        Bus::assertDispatched(SimulateWebhookJob::class, function (SimulateWebhookJob $job) use ($withdrawal) {
            return $job->type === 'withdraw' && $job->modelId === $withdrawal->id;
        });
    }
}
