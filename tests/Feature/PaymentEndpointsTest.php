<?php

namespace Tests\Feature;

use App\Jobs\SimulateWebhookJob;
use App\Models\Account;
use App\Models\Movement;
use App\Models\PixPayment;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class PaymentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pix_endpoint_creates_records_and_dispatches_webhook_job(): void
    {
        Bus::fake();
        $account = Account::factory()->create();

        $payload = [
            'amount' => 150.75,
            'account_id' => $account->id,
        ];

        $response = $this->postJson('/api/pix', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'account_id' => $account->id,
            ]);

        $movement = Movement::first();
        $this->assertNotNull($movement);
        $this->assertEquals(Movement::TYPE_PIX, $movement->type);
        $this->assertEquals($payload['amount'], (float) $movement->amount);

        $pix = PixPayment::first();
        $this->assertNotNull($pix);
        $this->assertSame('PENDING', $pix->status);
        $this->assertSame($movement->id, $pix->movement_id);

        Bus::assertDispatched(SimulateWebhookJob::class, function (SimulateWebhookJob $job) use ($movement) {
            return $job->movementId === $movement->id;
        });
    }

    public function test_withdraw_endpoint_creates_records_and_dispatches_webhook_job(): void
    {
        Bus::fake();
        $account = Account::factory()->create();

        $payload = [
            'amount' => 320.10,
            'account_id' => $account->id,
        ];

        $response = $this->postJson('/api/withdraw', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'account_id' => $account->id,
            ]);

        $movement = Movement::where('type', Movement::TYPE_WITHDRAW)->first();
        $this->assertNotNull($movement);
        $this->assertEquals($payload['amount'], (float) $movement->amount);

        $withdrawal = Withdrawal::first();
        $this->assertNotNull($withdrawal);
        $this->assertSame('PENDING', $withdrawal->status);
        $this->assertSame($movement->id, $withdrawal->movement_id);

        Bus::assertDispatched(SimulateWebhookJob::class, function (SimulateWebhookJob $job) use ($movement) {
            return $job->movementId === $movement->id;
        });
    }
}
