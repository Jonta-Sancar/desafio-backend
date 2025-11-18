<?php

namespace App\Services\Subadquirente;

use App\Models\Account;
use App\Models\Movement;
use App\Models\PixPayment;
use App\Models\Withdrawal;

interface SubadquirenteInterface
{
    public function createPix(Account $account, Movement $movement, array $payload): array;

    public function createWithdraw(Account $account, Movement $movement, array $payload): array;

    public function simulatePixWebhook(PixPayment $pixPayment): array;

    public function simulateWithdrawWebhook(Withdrawal $withdrawal): array;

    /**
     * Processa um payload real de webhook e retorna dados normalizados
     * [
     *   'identifier' => string,
     *   'status' => string,
     *   'payload' => array
     * ]
     */
    public function processPixWebhook(array $payload): array;

    public function processWithdrawWebhook(array $payload): array;
}
