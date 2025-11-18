<?php

namespace App\Services\Subadquirente;

use App\Models\Account;
use App\Models\Movement;
use App\Models\PixPayment;
use App\Models\Withdrawal;
use Illuminate\Support\Str;

class SubadqAService implements SubadquirenteInterface
{
    public function createPix(Account $account, Movement $movement, array $payload): array
    {
        $pixId = 'PIX'.strtoupper(Str::random(10));

        return [
            'pix_id' => $pixId,
            'transaction_id' => Str::uuid()->toString(),
            'status' => Movement::STATUS_PENDING,
            'meta' => [
                'subadquirente' => 'SubadqA',
                'account_label' => $account->label,
                'request' => $payload,
            ],
        ];
    }

    public function createWithdraw(Account $account, Movement $movement, array $payload): array
    {
        $withdrawId = 'WD'.strtoupper(Str::random(10));

        return [
            'withdraw_id' => $withdrawId,
            'transaction_id' => Str::uuid()->toString(),
            'status' => Movement::STATUS_PENDING,
            'meta' => [
                'subadquirente' => 'SubadqA',
                'account_label' => $account->label,
                'request' => $payload,
            ],
        ];
    }

    public function simulatePixWebhook(PixPayment $pixPayment): array
    {
        return [
            'event' => 'pix_payment_confirmed',
            'transaction_id' => $pixPayment->transaction_id ?? Str::uuid()->toString(),
            'pix_id' => $pixPayment->pix_id,
            'status' => 'CONFIRMED',
            'amount' => (float) $pixPayment->amount,
            'payer_name' => data_get($pixPayment->meta, 'request.payer_name', 'Cliente SubadqA'),
            'payer_cpf' => data_get($pixPayment->meta, 'request.payer_cpf', '12345678900'),
            'payment_date' => now()->toIso8601String(),
            'metadata' => [
                'source' => 'SubadqA',
                'environment' => app()->environment(),
            ],
        ];
    }

    public function simulateWithdrawWebhook(Withdrawal $withdrawal): array
    {
        return [
            'event' => 'withdraw_completed',
            'withdraw_id' => $withdrawal->withdraw_id,
            'transaction_id' => $withdrawal->transaction_id ?? Str::uuid()->toString(),
            'status' => 'SUCCESS',
            'amount' => (float) $withdrawal->amount,
            'requested_at' => now()->subMinutes(5)->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
            'metadata' => [
                'source' => 'SubadqA',
                'destination_bank' => data_get($withdrawal->meta, 'request.bank', '0001'),
            ],
        ];
    }

    public function processPixWebhook(array $payload): array
    {
        return [
            'identifier' => $payload['pix_id'] ?? null,
            'status' => $this->normalizeStatus($payload['status'] ?? Movement::STATUS_PENDING),
            'payload' => $payload,
        ];
    }

    public function processWithdrawWebhook(array $payload): array
    {
        return [
            'identifier' => $payload['withdraw_id'] ?? null,
            'status' => $this->normalizeStatus($payload['status'] ?? Movement::STATUS_PENDING),
            'payload' => $payload,
        ];
    }

    protected function normalizeStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'CONFIRMED' => 'CONFIRMED',
            'PAID' => 'PAID',
            'SUCCESS' => 'SUCCESS',
            'DONE' => 'DONE',
            'FAILED' => 'FAILED',
            'CANCELLED' => 'CANCELLED',
            'PROCESSING' => 'PROCESSING',
            default => Movement::STATUS_PENDING,
        };
    }
}
