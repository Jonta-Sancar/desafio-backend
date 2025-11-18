<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SimulateWebhookJob;
use App\Models\PixPayment;
use App\Services\Subadquirente\SubadqAService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PixController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'user_id' => 'nullable|exists:users,id',
            'subadq' => 'nullable|string',
        ]);

        $service = new SubadqAService();
        $resp = $service->createPix($data);

        $pix = PixPayment::create([
            'pix_id' => $resp['pix_id'],
            'user_id' => $data['user_id'] ?? null,
            'amount' => $data['amount'],
            'status' => $resp['status'],
            'meta' => $resp['meta'],
        ]);

        SimulateWebhookJob::dispatch('pix', $pix->id)->delay(now()->addSeconds(2));

        return response()->json($pix, Response::HTTP_CREATED);
    }
}
