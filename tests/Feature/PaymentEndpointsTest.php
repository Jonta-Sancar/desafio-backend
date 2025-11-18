<?php

namespace Tests\Feature;

use App\Jobs\SimulateWebhookJob;
use App\Models\Account;
use App\Models\Movement;
use App\Models\PixPayment;
use App\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
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
            'order' => 'order_test',
            'expires_in' => 1800,
            'payer' => [
                'name' => 'Fulano',
                'cpf_cnpj' => '12345678900',
            ],
        ];

        $response = $this->postJson('/api/pix', $payload);

        $response->assertCreated()
            ->assertJsonStructure([
                'pix_id',
                'transaction_id',
                'status',
                'amount',
                'order',
                'payer' => ['name', 'cpf_cnpj'],
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
            'transaction_id' => 'SP123456789',
            'bank_account' => [
                'bank_code' => '001',
                'agencia' => '1234',
                'conta' => '00012345',
                'type' => 'checking',
            ],
        ];

        $response = $this->postJson('/api/withdraw', $payload);

        $response->assertCreated()
            ->assertJsonStructure([
                'withdraw_id',
                'transaction_id',
                'status',
                'amount',
                'bank_account' => ['bank_code', 'agencia', 'conta', 'type'],
            ]);

        $movement = Movement::where('type', Movement::TYPE_WITHDRAW)->first();
        $this->assertNotNull($movement);
        $this->assertEquals($payload['amount'], (float) $movement->amount);

        $withdrawal = Withdrawal::first();
        $this->assertNotNull($withdrawal);
        $this->assertSame('PROCESSING', $withdrawal->status);
        $this->assertSame($movement->id, $withdrawal->movement_id);

        Bus::assertDispatched(SimulateWebhookJob::class, function (SimulateWebhookJob $job) use ($movement) {
            return $job->movementId === $movement->id;
        });
    }

    public function test_unknown_api_route_returns_forbidden(): void
    {
        $response = $this->getJson('/api/rota-nao-existente');

        $response->assertStatus(Response::HTTP_FORBIDDEN)
            ->assertJson([
                'message' => 'Forbidden. Verifique credenciais ou utilize os endpoints documentados.',
            ]);
    }
}
