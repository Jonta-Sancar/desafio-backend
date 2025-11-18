<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SimulateWebhookJob;
use App\Models\Account;
use App\Models\Movement;
use App\Models\PixPayment;
use App\Services\Subadquirente\SubadquirenteManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PixController extends Controller
{
    public function __construct(private readonly SubadquirenteManager $subadquirenteManager)
    {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'metadata' => 'sometimes|array',
        ]);

        /** @var Account $account */
        $account = Account::query()->where('active', true)->findOrFail($data['account_id']);

        $movement = Movement::create([
            'account_id' => $account->id,
            'type' => Movement::TYPE_PIX,
            'status' => Movement::STATUS_CREATED,
            'amount' => $data['amount'],
            'payload' => [
                'request' => $request->all(),
            ],
        ]);

        $service = $this->subadquirenteManager->resolve($account->provider);
        $serviceResponse = $service->createPix($account, $movement, $data);

        $pix = PixPayment::create([
            'movement_id' => $movement->id,
            'account_id' => $account->id,
            'pix_id' => $serviceResponse['pix_id'],
            'transaction_id' => $serviceResponse['transaction_id'] ?? null,
            'amount' => $data['amount'],
            'status' => $serviceResponse['status'] ?? Movement::STATUS_PENDING,
            'meta' => array_merge($serviceResponse['meta'] ?? [], $data['metadata'] ?? []),
        ]);

        $movement->update([
            'status' => $pix->status,
        ]);

        if (config('subadquirentes.webhook_mode') === 'simulation') {
            SimulateWebhookJob::dispatch($movement->id)->delay(now()->addSeconds(2));
        }

        return response()->json($pix->load('movement'), Response::HTTP_CREATED);
    }
}
