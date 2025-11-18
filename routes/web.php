<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api')->group(function () {
    Route::post('pix', [\App\Http\Controllers\Api\PixController::class, 'store']);
    Route::post('withdraw', [\App\Http\Controllers\Api\WithdrawController::class, 'store']);
});
