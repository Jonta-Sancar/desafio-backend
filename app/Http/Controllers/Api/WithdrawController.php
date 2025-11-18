<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Movements\MovementServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WithdrawController extends Controller
{
    public function __construct(private readonly MovementServiceInterface $movementService)
    {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'transaction_id' => 'nullable|string|max:100',
            'bank_account.bank_code' => 'required|string|max:10',
            'bank_account.agencia' => 'required|string|max:20',
            'bank_account.conta' => 'required|string|max:30',
            'bank_account.type' => 'required|string|max:20',
        ]);

        /** @var Account|null $account */
        $account = Account::query()
            ->where('active', true)
            ->find($data['account_id']);

        if (! $account) {
            return response()->json([
                'message' => 'Conta não encontrada ou inativa.',
            ], Response::HTTP_NOT_FOUND);
        }

        $result = $this->movementService->createWithdraw($account, $data);

        return response()->json($result['response'], Response::HTTP_CREATED);
    }
}
