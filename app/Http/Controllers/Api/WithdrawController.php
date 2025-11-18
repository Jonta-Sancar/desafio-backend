<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SimulateWebhookJob;
use App\Models\Withdrawal;
use App\Services\Subadquirente\SubadqAService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WithdrawController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'user_id' => 'nullable|exists:users,id',
            'subadq' => 'nullable|string',
        ]);

        $service = new SubadqAService();
        $resp = $service->createWithdraw($data);

        $wd = Withdrawal::create([
            'withdraw_id' => $resp['withdraw_id'],
            'user_id' => $data['user_id'] ?? null,
            'amount' => $data['amount'],
            'status' => $resp['status'],
            'meta' => $resp['meta'],
        ]);

        SimulateWebhookJob::dispatch('withdraw', $wd->id)->delay(now()->addSeconds(3));

        return response()->json($wd, Response::HTTP_CREATED);
    }
}
