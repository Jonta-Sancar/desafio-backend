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

class PixController extends Controller
{
    public function __construct(private readonly MovementServiceInterface $movementService)
    {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'order' => 'nullable|string|max:100',
            'expires_in' => 'nullable|integer|min:60',
            'payer.name' => 'required|string|max:255',
            'payer.cpf_cnpj' => 'required|string|max:20',
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

            $result = $this->movementService->createPix($account, $data);

            return response()->json($result['response'], Response::HTTP_CREATED);
        } catch (InvalidArgumentException $e) {
            Log::warning('Erro de validação ao criar PIX', [
                'account_id' => $data['account_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (Throwable $e) {
            Log::error('Erro inesperado ao processar PIX', [
                'account_id' => $data['account_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erro interno ao processar o PIX.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
