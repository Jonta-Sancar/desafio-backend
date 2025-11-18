<?php

return [
    'webhook_mode' => env('SUBADQ_WEBHOOK_MODE', 'simulation'),
    'pix_expires_in' => env('SUBADQ_PIX_EXPIRES_IN', 3600),

    'providers' => [
        'subadq_a' => App\Services\Subadquirente\SubadqAService::class,
        'subadq_b' => App\Services\Subadquirente\SubadqBService::class,
    ],

    'http' => [
        'subadq_a' => [
            'base_url' => env('SUBADQA_BASE_URL', 'https://0acdeaee-1729-4d55-80eb-d54a125e5e18.mock.pstmn.io'),
            'pix' => [
                'path' => '/pix/create',
                'headers' => [
                    'x-mock-response-name' => 'SUCESSO_PIX',
                ],
            ],
            'withdraw' => [
                'path' => '/withdraw',
                'headers' => [
                    'x-mock-response-name' => 'SUCESSO_WD',
                ],
            ],
        ],
        'subadq_b' => [
            'base_url' => env('SUBADQB_BASE_URL', 'https://ef8513c8-fd99-4081-8963-573cd135e133.mock.pstmn.io'),
            'pix' => [
                'path' => '/pix/create',
                'headers' => [
                    'x-mock-response-name' => 'SUCESSO_PIX',
                ],
            ],
            'withdraw' => [
                'path' => '/withdraw',
                'headers' => [
                    'x-mock-response-name' => 'SUCESSO_WD',
                ],
            ],
        ],
    ],
];
