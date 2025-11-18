<?php

namespace App\Services\Movements;

use App\Models\Account;

interface MovementServiceInterface
{
    /**
     * @return array{movement: \App\Models\Movement, response: array}
     */
    public function createPix(Account $account, array $payload): array;

    /**
     * @return array{movement: \App\Models\Movement, response: array}
     */
    public function createWithdraw(Account $account, array $payload): array;

    public function getBalance(Account $account): float;
}
