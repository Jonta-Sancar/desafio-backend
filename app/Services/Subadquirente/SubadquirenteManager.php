<?php

namespace App\Services\Subadquirente;

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;

class SubadquirenteManager
{
    public function __construct(private readonly Application $app)
    {
    }

    public function resolve(string $provider): SubadquirenteInterface
    {
        $providers = config('subadquirentes.providers', []);
        $key = strtolower($provider);

        if (! array_key_exists($key, $providers)) {
            throw new InvalidArgumentException("Subadquirente [{$provider}] nao suportada.");
        }

        $service = $this->app->make($providers[$key]);

        if (! $service instanceof SubadquirenteInterface) {
            throw new InvalidArgumentException("Servico configurado para [{$provider}] invalido.");
        }

        return $service;
    }
}
