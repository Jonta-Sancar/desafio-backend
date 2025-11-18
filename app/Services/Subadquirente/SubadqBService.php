<?php

namespace App\Services\Subadquirente;

use App\Models\Account;
use App\Models\Movement;
use App\Models\PixPayment;
use App\Models\Withdrawal;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

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

        $response = $this->dispatchPixRequest('subadq_b', $requestPayload);

        return [
            'pix_id' => $pixId,
            'transaction_id' => $response['transaction_id'] ?? Str::uuid()->toString(),
            'status' => $this->normalizeStatus($response['status'] ?? Movement::STATUS_PENDING),
            'meta' => [
                'subadquirente' => 'SubadqB',
                'account_label' => $account->label,
                'request_payload' => $requestPayload,
                'response_payload' => $response,
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

        $responsePayload = $this->dispatchWithdrawRequest('subadq_b', $requestPayload);

        return [
            'withdraw_id' => $responsePayload['withdraw_id'] ?? $withdrawId,
            'transaction_id' => $responsePayload['transaction_id'] ?? $payload['transaction_id'],
            'status' => $this->normalizeStatus($responsePayload['status'] ?? Movement::STATUS_PENDING),
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

    protected function dispatchPixRequest(string $provider, array $payload): array
    {
        return $this->dispatchRequest($provider, 'pix', $payload, 'Não foi possível gerar o PIX no provedor '.$provider.'.');
    }

    protected function dispatchWithdrawRequest(string $provider, array $payload): array
    {
        return $this->dispatchRequest($provider, 'withdraw', $payload, 'Não foi possível registrar o saque no provedor '.$provider.'.');
    }

    protected function dispatchRequest(string $provider, string $channel, array $payload, string $errorMessage): array
    {
        if ($this->shouldFakeRequests()) {
            return $this->fakeResponse($provider, $channel, $payload);
        }

        $httpConfig = config("subadquirentes.http.$provider");
        $channelConfig = data_get($httpConfig, $channel, []);
        $path = ltrim(data_get($channelConfig, 'path', ''), '/');

        try {
            return Http::baseUrl($httpConfig['base_url'])
                ->acceptJson()
                ->withHeaders($channelConfig['headers'] ?? [])
                ->post($path, $payload)
                ->throw()
                ->json();
        } catch (Throwable $e) {
            Log::error("Erro ao chamar subadquirente {$provider} via {$channel}", [
                'error' => $e->getMessage(),
            ]);

            throw new InvalidArgumentException($errorMessage);
        }
    }

    protected function shouldFakeRequests(): bool
    {
        return app()->environment('testing');
    }

    protected function fakeResponse(string $provider, string $channel, array $payload): array
    {
        if ($channel === 'pix') {
            return [
                'transaction_id' => 'SP_'.strtoupper($provider).'_FAKE',
                'location' => "https://{$provider}.mock/pix/loc/888",
                'qrcode' => 'qrcode-'.$provider,
                'expires_at' => (string) now()->addHour()->timestamp,
                'status' => $provider === 'subadq_a' ? 'PENDING' : 'PROCESSING',
            ];
        }

        if ($channel === 'withdraw') {
            return [
                'withdraw_id' => 'WD_'.strtoupper($provider).'_FAKE',
                'transaction_id' => $payload['transaction_id'],
                'status' => $provider === 'subadq_a' ? 'PROCESSING' : 'DONE',
            ];
        }

        return [];
    }
}
