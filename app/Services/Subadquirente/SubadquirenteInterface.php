<?php

namespace App\Services\Subadquirente;

interface SubadquirenteInterface
{
    /**
     * Cria um PIX via subadquirente
     * @param array $payload
     * @return array
     */
    public function createPix(array $payload): array;

    /**
     * Cria um saque via subadquirente
     * @param array $payload
     * @return array
     */
    public function createWithdraw(array $payload): array;
}
