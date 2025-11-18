<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PixPayment;
use App\Models\Withdrawal;
use App\Services\Subadquirente\SubadquirenteManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(private readonly SubadquirenteManager $subadquirenteManager)
    {
    }

    public function pix(Request $request, string $provider): JsonResponse
    {
        try {
            $service = $this->subadquirenteManager->resolve($provider);
            $result = $service->processPixWebhook($request->all());

            $pix = PixPayment::query()
                ->where('pix_id', $result['identifier'])
                ->firstOrFail();

            $pix->update([
                'status' => $result['status'],
                'meta' => array_merge($pix->meta ?? [], ['webhook_payload' => $result['payload']]),
            ]);

            $pix->movement->update([
                'status' => $result['status'],
                'processed_at' => now(),
            ]);

            return response()->json(['processed' => true]);
        } catch (Throwable $e) {
            Log::error('Erro ao processar webhook de PIX', [
                'provider' => $provider,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao processar webhook de PIX.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function withdraw(Request $request, string $provider): JsonResponse
    {
        try {
            $service = $this->subadquirenteManager->resolve($provider);
            $result = $service->processWithdrawWebhook($request->all());

            $withdrawal = Withdrawal::query()
                ->where('withdraw_id', $result['identifier'])
                ->firstOrFail();

            $withdrawal->update([
                'status' => $result['status'],
                'meta' => array_merge($withdrawal->meta ?? [], ['webhook_payload' => $result['payload']]),
            ]);

            $withdrawal->movement->update([
                'status' => $result['status'],
                'processed_at' => now(),
            ]);

            return response()->json(['processed' => true]);
        } catch (Throwable $e) {
            Log::error('Erro ao processar webhook de saque', [
                'provider' => $provider,
                'payload' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro ao processar webhook de saque.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
