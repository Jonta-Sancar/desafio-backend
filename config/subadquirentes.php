<?php

return [
    'webhook_mode' => env('SUBADQ_WEBHOOK_MODE', 'simulation'),
    'pix_expires_in' => env('SUBADQ_PIX_EXPIRES_IN', 3600),

    'providers' => [
        'subadq_a' => App\Services\Subadquirente\SubadqAService::class,
        'subadq_b' => App\Services\Subadquirente\SubadqBService::class,
    ],
];
