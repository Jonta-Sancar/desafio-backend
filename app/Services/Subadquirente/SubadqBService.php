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
        $amountInCents = (int) round($payload['amount'] * 100);

        $requestPayload = [
            'seller_id' => $payload['seller_id'],
            'amount' => $amountInCents,
            'order' => $payload['order'],
            'payer' => [
                'name' => $payload['payer']['name'],
                'cpf_cnpj' => $payload['payer']['cpf_cnpj'],
            ],
            'expires_in' => $payload['expires_in'],
        ];

        $locationCode = random_int(400, 999);
        $mockedResponse = [
            'transaction_id' => 'SP_ADQB_'.Str::uuid()->toString(),
            'location' => "https://subadqB.com/pix/loc/{$locationCode}",
            'qrcode' => '00020126530014BR.GOV.BCB.PIX0131backendtest@superpagamentos.com52040000530398654075000.005802BR5901N6001C6205050116304ACDA',
            'expires_at' => (string) now()->addHour()->timestamp,
            'status' => 'PROCESSING',
        ];

        return [
            'pix_id' => $pixId,
            'transaction_id' => $mockedResponse['transaction_id'],
            'status' => $mockedResponse['status'],
            'meta' => [
                'subadquirente' => 'SubadqB',
                'account_label' => $account->label,
                'request_payload' => $requestPayload,
                'response_payload' => $mockedResponse,
            ],
        ];
    }

    public function createWithdraw(Account $account, Movement $movement, array $payload): array
    {
        $withdrawId = 'WD_ADQB_'.Str::uuid()->toString();
        $requestPayload = [
            'merchant_id' => $payload['merchant_id'],
            'account' => [
                'bank_code' => $payload['bank_account']['bank_code'],
                'agencia' => $payload['bank_account']['agencia'],
                'conta' => $payload['bank_account']['conta'],
                'type' => $payload['bank_account']['type'],
            ],
            'amount' => (int) round($payload['amount'] * 100),
            'transaction_id' => $payload['transaction_id'],
        ];

        $responsePayload = [
            'withdraw_id' => $withdrawId,
            'status' => 'DONE',
        ];

        return [
            'withdraw_id' => $withdrawId,
            'transaction_id' => $payload['transaction_id'],
            'status' => 'DONE',
            'meta' => [
                'subadquirente' => 'SubadqB',
                'account_label' => $account->label,
                'request_payload' => $requestPayload,
                'response_payload' => $responsePayload,
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
                    'name' => data_get($pixPayment->meta, 'normalized_request.payer.name', 'Pagador B'),
                    'document' => data_get($pixPayment->meta, 'normalized_request.payer.cpf_cnpj', '98765432100'),
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
                    'bank' => data_get($withdrawal->meta, 'normalized_request.bank_account.bank_code', '0001'),
                    'agency' => data_get($withdrawal->meta, 'normalized_request.bank_account.agencia', '0001'),
                    'account' => data_get($withdrawal->meta, 'normalized_request.bank_account.conta', '1234567-8'),
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
