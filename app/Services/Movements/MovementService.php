<?php

namespace App\Services\Movements;

use App\Jobs\SimulateWebhookJob;
use App\Models\Account;
use App\Models\Movement;
use App\Models\PixPayment;
use App\Models\Withdrawal;
use App\Services\Subadquirente\SubadquirenteManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class MovementService implements MovementServiceInterface
{
    public function __construct(private readonly SubadquirenteManager $subadquirenteManager)
    {
    }

    public function createPix(Account $account, array $payload): array
    {
        try {
            return DB::transaction(function () use ($account, $payload) {
                $normalized = $this->transformPixRequest($account, $payload);

                $movement = Movement::create([
                    'account_id' => $account->id,
                    'type' => Movement::TYPE_PIX,
                    'status' => Movement::STATUS_CREATED,
                    'amount' => $normalized['amount'],
                    'payload' => [
                        'request' => $payload,
                    ],
                ]);

                $providerService = $this->subadquirenteManager->resolve($account->provider);
                $serviceResponse = $providerService->createPix($account, $movement, $normalized);

                $pix = PixPayment::create([
                    'movement_id' => $movement->id,
                    'account_id' => $account->id,
                    'pix_id' => $serviceResponse['pix_id'],
                    'transaction_id' => $serviceResponse['transaction_id'] ?? null,
                    'amount' => $normalized['amount'],
                    'status' => $serviceResponse['status'] ?? Movement::STATUS_PENDING,
                    'meta' => array_merge(
                        ['normalized_request' => $normalized],
                        Arr::get($serviceResponse, 'meta', [])
                    ),
                ]);

                $movement->update([
                    'status' => $pix->status,
                ]);

                $this->maybeDispatchWebhookJob($movement);

                return [
                    'movement' => $movement->load('pixPayment'),
                    'response' => $this->formatPixResponse($pix),
                ];
            });
        } catch (Throwable $e) {
            Log::error('Erro ao criar PIX', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function createWithdraw(Account $account, array $payload): array
    {
        try {
            return DB::transaction(function () use ($account, $payload) {
                $lockedAccount = Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
                $normalized = $this->transformWithdrawRequest($lockedAccount, $payload);
                $this->ensureSufficientBalance($lockedAccount, $normalized['amount']);

                $movement = Movement::create([
                    'account_id' => $lockedAccount->id,
                    'type' => Movement::TYPE_WITHDRAW,
                    'status' => Movement::STATUS_CREATED,
                    'amount' => $normalized['amount'],
                    'payload' => [
                        'request' => $payload,
                    ],
                ]);

                $providerService = $this->subadquirenteManager->resolve($lockedAccount->provider);
                $serviceResponse = $providerService->createWithdraw($lockedAccount, $movement, $normalized);

                $withdrawal = Withdrawal::create([
                    'movement_id' => $movement->id,
                    'account_id' => $lockedAccount->id,
                    'withdraw_id' => $serviceResponse['withdraw_id'],
                    'transaction_id' => $serviceResponse['transaction_id'] ?? $normalized['transaction_id'],
                    'amount' => $normalized['amount'],
                    'status' => $serviceResponse['status'] ?? Movement::STATUS_PENDING,
                    'meta' => array_merge(
                        ['normalized_request' => $normalized],
                        Arr::get($serviceResponse, 'meta', [])
                    ),
                ]);

                $movement->update([
                    'status' => $withdrawal->status,
                ]);

                $this->maybeDispatchWebhookJob($movement);

                return [
                    'movement' => $movement->load('withdrawal'),
                    'response' => $this->formatWithdrawResponse($withdrawal),
                ];
            });
        } catch (Throwable $e) {
            Log::error('Erro ao criar saque', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function transformPixRequest(Account $account, array $payload): array
    {
        if (! isset($payload['amount'])) {
            throw new InvalidArgumentException('Campo amount é obrigatório para PIX.');
        }

        $merchantId = data_get($account->settings, 'merchant_id') ?? data_get($account->settings, 'seller_id');
        if (! $merchantId) {
            throw new InvalidArgumentException('Conta sem merchant_id/seller_id configurado.');
        }

        $payerName = data_get($payload, 'payer.name');
        $payerDocument = data_get($payload, 'payer.cpf_cnpj');
        if (! $payerName || ! $payerDocument) {
            throw new InvalidArgumentException('Dados do pagador (name, cpf_cnpj) são obrigatórios.');
        }

        $order = $payload['order'] ?? sprintf('order_%s_%s', $account->id, now()->format('YmdHis'));
        $expiresIn = (int) config('subadquirentes.pix_expires_in', 3600);

        return [
            'merchant_id' => $merchantId,
            'seller_id' => data_get($account->settings, 'seller_id', $merchantId),
            'amount' => (float) $payload['amount'],
            'order' => $order,
            'payer' => [
                'name' => $payerName,
                'cpf_cnpj' => $payerDocument,
            ],
            'expires_in' => $expiresIn,
            'currency' => 'BRL',
        ];
    }

    protected function transformWithdrawRequest(Account $account, array $payload): array
    {
        if (! isset($payload['amount'])) {
            throw new InvalidArgumentException('Campo amount é obrigatório para saque.');
        }

        $merchantId = data_get($account->settings, 'merchant_id');
        if (! $merchantId) {
            throw new InvalidArgumentException('Conta sem merchant_id configurado para saque.');
        }

        $bankAccount = $payload['bank_account'] ?? data_get($account->settings, 'bank_account');
        if (! is_array($bankAccount)) {
            throw new InvalidArgumentException('Dados bancários são obrigatórios.');
        }

        foreach (['bank_code', 'agencia', 'conta', 'type'] as $field) {
            if (! isset($bankAccount[$field])) {
                throw new InvalidArgumentException("Campo {$field} é obrigatório dentro de bank_account.");
            }
        }

        return [
            'merchant_id' => $merchantId,
            'amount' => (float) $payload['amount'],
            'transaction_id' => $payload['transaction_id'] ?? 'SP'.Str::uuid()->toString(),
            'bank_account' => [
                'bank_code' => $bankAccount['bank_code'],
                'agencia' => $bankAccount['agencia'],
                'conta' => $bankAccount['conta'],
                'type' => $bankAccount['type'],
            ],
        ];
    }

    protected function formatPixResponse(PixPayment $pix): array
    {
        $responsePayload = data_get($pix->meta, 'response_payload', []);
        $normalized = data_get($pix->meta, 'normalized_request', []);

        return [
            'movement_id' => $pix->movement_id,
            'provider' => $pix->account?->provider,
            'pix_id' => $pix->pix_id,
            'transaction_id' => $pix->transaction_id,
            'amount' => (float) $pix->amount,
            'currency' => $normalized['currency'] ?? 'BRL',
            'order' => $normalized['order'] ?? null,
            'payer' => $normalized['payer'] ?? null,
            'expires_in' => $normalized['expires_in'] ?? null,
            'location' => $responsePayload['location'] ?? null,
            'qrcode' => $responsePayload['qrcode'] ?? null,
            'expires_at' => $responsePayload['expires_at'] ?? null,
            'status' => $pix->status,
        ];
    }

    public function getBalance(Account $account): float
    {
        return $this->calculateAvailableBalance($account);
    }

    protected function formatWithdrawResponse(Withdrawal $withdrawal): array
    {
        $normalized = data_get($withdrawal->meta, 'normalized_request', []);

        return [
            'movement_id' => $withdrawal->movement_id,
            'provider' => $withdrawal->account?->provider,
            'withdraw_id' => $withdrawal->withdraw_id,
            'transaction_id' => $withdrawal->transaction_id,
            'amount' => (float) $withdrawal->amount,
            'bank_account' => $normalized['bank_account'] ?? null,
            'status' => $withdrawal->status,
        ];
    }

    protected function calculateAvailableBalance(Account $account): float
    {
        $invalidStatuses = ['CANCELLED', 'FAILED'];

        $entries = PixPayment::query()
            ->where('account_id', $account->id)
            ->whereNotIn('status', $invalidStatuses)
            ->sum('amount');

        $outputs = Withdrawal::query()
            ->where('account_id', $account->id)
            ->whereNotIn('status', $invalidStatuses)
            ->sum('amount');

        return (float) $entries - (float) $outputs;
    }

    protected function ensureSufficientBalance(Account $account, float $amount): void
    {
        $balance = $this->calculateAvailableBalance($account);

        if ($balance < $amount) {
            throw new InvalidArgumentException(
                sprintf('Saldo insuficiente para o saque solicitado. Saldo atual: %.2f', $balance)
            );
        }
    }

    protected function maybeDispatchWebhookJob(Movement $movement): void
    {
        if (config('subadquirentes.webhook_mode') === 'simulation') {
            SimulateWebhookJob::dispatch($movement->id)->delay(
                now()->addSeconds($movement->type === Movement::TYPE_PIX ? 2 : 3)
            );
        }
    }
}
