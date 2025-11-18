<?php

namespace App\Services\Subadquirente;

use App\Models\Account;
use App\Models\Movement;
use App\Models\PixPayment;
use App\Models\Withdrawal;
use Illuminate\Support\Str;

class SubadqBService implements SubadquirenteInterface
{
    public function createPix(Account $account, Movement $movement, array $payload): array
    {
        $pixId = 'BXP'.strtoupper(Str::random(10));

        return [
            'pix_id' => $pixId,
            'transaction_id' => 'TX'.strtoupper(Str::random(12)),
            'status' => Movement::STATUS_PENDING,
            'meta' => [
                'subadquirente' => 'SubadqB',
                'account_label' => $account->label,
                'request' => $payload,
            ],
        ];
    }

    public function createWithdraw(Account $account, Movement $movement, array $payload): array
    {
        $withdrawId = 'BWD'.strtoupper(Str::random(10));

        return [
            'withdraw_id' => $withdrawId,
            'transaction_id' => 'TW'.strtoupper(Str::random(12)),
            'status' => Movement::STATUS_PENDING,
            'meta' => [
                'subadquirente' => 'SubadqB',
                'account_label' => $account->label,
                'request' => $payload,
            ],
        ];
    }

    public function simulatePixWebhook(PixPayment $pixPayment): array
    {
        return [
            'type' => 'pix.status_update',
            'data' => [
                'id' => $pixPayment->pix_id,
                'status' => 'PAID',
                'value' => (float) $pixPayment->amount,
                'payer' => [
                    'name' => data_get($pixPayment->meta, 'request.payer.name', 'Pagador B'),
                    'document' => data_get($pixPayment->meta, 'request.payer.document', '98765432100'),
                ],
                'confirmed_at' => now()->toIso8601String(),
            ],
            'signature' => Str::random(24),
        ];
    }

    public function simulateWithdrawWebhook(Withdrawal $withdrawal): array
    {
        return [
            'type' => 'withdraw.status_update',
            'data' => [
                'id' => $withdrawal->withdraw_id,
                'status' => 'DONE',
                'amount' => (float) $withdrawal->amount,
                'bank_account' => [
                    'bank' => data_get($withdrawal->meta, 'request.bank_account.bank', '0001'),
                    'agency' => data_get($withdrawal->meta, 'request.bank_account.agency', '0001'),
                    'account' => data_get($withdrawal->meta, 'request.bank_account.account', '1234567-8'),
                ],
                'processed_at' => now()->toIso8601String(),
            ],
            'signature' => Str::random(24),
        ];
    }

    public function processPixWebhook(array $payload): array
    {
        $data = $payload['data'] ?? [];

        return [
            'identifier' => $data['id'] ?? null,
            'status' => $this->normalizeStatus($data['status'] ?? Movement::STATUS_PENDING),
            'payload' => $payload,
        ];
    }

    public function processWithdrawWebhook(array $payload): array
    {
        $data = $payload['data'] ?? [];

        return [
            'identifier' => $data['id'] ?? null,
            'status' => $this->normalizeStatus($data['status'] ?? Movement::STATUS_PENDING),
            'payload' => $payload,
        ];
    }

    protected function normalizeStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID' => 'PAID',
            'DONE' => 'DONE',
            'SUCCESS' => 'SUCCESS',
            'FAILED' => 'FAILED',
            'CANCELLED' => 'CANCELLED',
            'PROCESSING' => 'PROCESSING',
            default => Movement::STATUS_PENDING,
        };
    }
}
