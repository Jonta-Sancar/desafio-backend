<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SimulateWebhookJob;
use App\Models\Account;
use App\Models\Movement;
use App\Models\Withdrawal;
use App\Services\Subadquirente\SubadquirenteManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WithdrawController extends Controller
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
            'type' => Movement::TYPE_WITHDRAW,
            'status' => Movement::STATUS_CREATED,
            'amount' => $data['amount'],
            'payload' => [
                'request' => $request->all(),
            ],
        ]);

        $service = $this->subadquirenteManager->resolve($account->provider);
        $serviceResponse = $service->createWithdraw($account, $movement, $data);

        $withdrawal = Withdrawal::create([
            'movement_id' => $movement->id,
            'account_id' => $account->id,
            'withdraw_id' => $serviceResponse['withdraw_id'],
            'transaction_id' => $serviceResponse['transaction_id'] ?? null,
            'amount' => $data['amount'],
            'status' => $serviceResponse['status'] ?? Movement::STATUS_PENDING,
            'meta' => array_merge($serviceResponse['meta'] ?? [], $data['metadata'] ?? []),
        ]);

        $movement->update([
            'status' => $withdrawal->status,
        ]);

        if (config('subadquirentes.webhook_mode') === 'simulation') {
            SimulateWebhookJob::dispatch($movement->id)->delay(now()->addSeconds(3));
        }

        return response()->json($withdrawal->load('movement'), Response::HTTP_CREATED);
    }
}
