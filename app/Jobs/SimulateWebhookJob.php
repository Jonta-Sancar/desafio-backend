<?php

namespace App\Jobs;

use App\Models\Movement;
use App\Services\Subadquirente\SubadquirenteManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class SimulateWebhookJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $movementId)
    {
    }

    public function handle(SubadquirenteManager $subadquirenteManager): void
    {
        $movement = Movement::query()
            ->with(['account', 'pixPayment', 'withdrawal'])
            ->find($this->movementId);

        if (! $movement || ! $movement->account) {
            return;
        }

        $service = $subadquirenteManager->resolve($movement->account->provider);

        if ($movement->type === Movement::TYPE_PIX && $movement->pixPayment) {
            $payload = $service->simulatePixWebhook($movement->pixPayment);
            $result = $service->processPixWebhook($payload);

            $movement->pixPayment->update([
                'status' => $result['status'],
                'meta' => array_merge($movement->pixPayment->meta ?? [], ['webhook_payload' => $result['payload']]),
            ]);

            $movement->update([
                'status' => $result['status'],
                'processed_at' => now(),
            ]);

            Log::info('Simulated webhook PIX processed', ['movement_id' => $movement->id]);

            return;
        }

        if ($movement->type === Movement::TYPE_WITHDRAW && $movement->withdrawal) {
            $payload = $service->simulateWithdrawWebhook($movement->withdrawal);
            $result = $service->processWithdrawWebhook($payload);

            $movement->withdrawal->update([
                'status' => $result['status'],
                'meta' => array_merge($movement->withdrawal->meta ?? [], ['webhook_payload' => $result['payload']]),
            ]);

            $movement->update([
                'status' => $result['status'],
                'processed_at' => now(),
            ]);

            Log::info('Simulated webhook withdrawal processed', ['movement_id' => $movement->id]);
        }
    }
}
