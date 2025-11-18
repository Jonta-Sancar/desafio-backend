<?php

return [
    'webhook_mode' => env('SUBADQ_WEBHOOK_MODE', 'simulation'),

    'providers' => [
        'subadq_a' => App\Services\Subadquirente\SubadqAService::class,
        'subadq_b' => App\Services\Subadquirente\SubadqBService::class,
    ],
];
