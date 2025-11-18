<?php

use Illuminate\Support\Facades\Route;

Route::middleware('api')->group(function () {
    Route::post('pix', [\App\Http\Controllers\Api\PixController::class, 'store']);
    Route::post('withdraw', [\App\Http\Controllers\Api\WithdrawController::class, 'store']);
});
