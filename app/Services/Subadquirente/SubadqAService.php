<?php

namespace App\Services\Subadquirente;

class SubadqAService implements SubadquirenteInterface
{
    public function createPix(array $payload): array
    {
        return [
            'pix_id' => 'PX'.uniqid(),
            'status' => 'PENDING',
            'meta' => $payload,
        ];
    }

    public function createWithdraw(array $payload): array
    {
        return [
            'withdraw_id' => 'WD'.uniqid(),
            'status' => 'PENDING',
            'meta' => $payload,
        ];
    }
}
