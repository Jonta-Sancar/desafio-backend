<?php

use App\Http\Controllers\Api\PixController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\WithdrawController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->group(function () {
    Route::post('pix', [PixController::class, 'store']);
    Route::post('withdraw', [WithdrawController::class, 'store']);

    Route::prefix('webhooks/{provider}')->group(function () {
        Route::post('pix', [WebhookController::class, 'pix']);
        Route::post('withdraw', [WebhookController::class, 'withdraw']);
    });
});
