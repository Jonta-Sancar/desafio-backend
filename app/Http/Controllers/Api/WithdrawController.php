<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Movements\MovementServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

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
            'bank_account' => 'nullable|array',
            'bank_account.bank_code' => 'required_with:bank_account|string|max:10',
            'bank_account.agencia' => 'required_with:bank_account|string|max:20',
            'bank_account.conta' => 'required_with:bank_account|string|max:30',
            'bank_account.type' => 'required_with:bank_account|string|max:20',
        ]);

        try {
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
        } catch (InvalidArgumentException $e) {
            Log::warning('Erro de validação ao criar saque', [
                'account_id' => $data['account_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            Log::error('Erro inesperado ao processar saque', [
                'account_id' => $data['account_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro interno ao processar o saque.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
