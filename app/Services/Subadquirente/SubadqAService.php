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

        $response = $this->dispatchPixRequest('subadq_a', $requestPayload);

        return [
            'pix_id' => $pixId,
            'transaction_id' => $response['transaction_id'] ?? Str::uuid()->toString(),
            'status' => $this->normalizeStatus($response['status'] ?? Movement::STATUS_PENDING),
            'meta' => [
                'subadquirente' => 'SubadqA',
                'account_label' => $account->label,
                'request_payload' => $requestPayload,
                'response_payload' => $response,
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

        $responsePayload = $this->dispatchWithdrawRequest('subadq_a', $requestPayload);

        return [
            'withdraw_id' => $responsePayload['withdraw_id'] ?? $withdrawId,
            'transaction_id' => $responsePayload['transaction_id'] ?? $payload['transaction_id'],
            'status' => $this->normalizeStatus($responsePayload['status'] ?? Movement::STATUS_PENDING),
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
                'location' => "https://{$provider}.mock/pix/loc/999",
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
