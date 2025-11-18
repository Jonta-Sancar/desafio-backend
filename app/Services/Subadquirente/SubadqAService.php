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
        $pixId = 'PIX'.strtoupper(Str::random(12));
        $amountInCents = (int) round($payload['amount'] * 100);
        $requestPayload = [
            'merchant_id' => $payload['merchant_id'],
            'amount' => $amountInCents,
            'currency' => 'BRL',
            'order_id' => $payload['order'],
            'payer' => [
                'name' => $payload['payer']['name'],
                'cpf_cnpj' => $payload['payer']['cpf_cnpj'],
            ],
            'expires_in' => $payload['expires_in'],
        ];

        $locationCode = random_int(100, 999);
        $mockedResponse = [
            'transaction_id' => 'SP_SUBADQA_'.Str::uuid()->toString(),
            'location' => "https://subadqA.com/pix/loc/{$locationCode}",
            'qrcode' => '00020126530014BR.GOV.BCB.PIX0131backendtest@superpagamentos.com52040000530398654075000.005802BR5901N6001C6205050116304ACDA',
            'expires_at' => (string) now()->addHour()->timestamp,
            'status' => Movement::STATUS_PENDING,
        ];

        return [
            'pix_id' => $pixId,
            'transaction_id' => $mockedResponse['transaction_id'],
            'status' => $mockedResponse['status'],
            'meta' => [
                'subadquirente' => 'SubadqA',
                'account_label' => $account->label,
                'request_payload' => $requestPayload,
                'response_payload' => $mockedResponse,
            ],
        ];
    }

    public function createWithdraw(Account $account, Movement $movement, array $payload): array
    {
        $withdrawId = 'WD'.strtoupper(Str::uuid()->toString());
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
            'status' => 'PROCESSING',
        ];

        return [
            'withdraw_id' => $withdrawId,
            'transaction_id' => $payload['transaction_id'],
            'status' => 'PROCESSING',
            'meta' => [
                'subadquirente' => 'SubadqA',
                'account_label' => $account->label,
                'request_payload' => $requestPayload,
                'response_payload' => $responsePayload,
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
            'payer_name' => data_get($pixPayment->meta, 'normalized_request.payer.name', 'Cliente SubadqA'),
            'payer_cpf' => data_get($pixPayment->meta, 'normalized_request.payer.cpf_cnpj', '12345678900'),
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
                'destination_bank' => data_get($withdrawal->meta, 'normalized_request.bank_account.bank_code', '0001'),
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
