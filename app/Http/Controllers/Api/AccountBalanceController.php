<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Movements\MovementServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AccountBalanceController extends Controller
{
    public function __construct(private readonly MovementServiceInterface $movementService)
    {
    }

    public function show(Account $account): JsonResponse
    {
        if (! $account->active) {
            return response()->json([
                'message' => 'Conta não encontrada ou inativa.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'account_id' => $account->id,
            'balance' => $this->movementService->getBalance($account),
        ]);
    }
}
