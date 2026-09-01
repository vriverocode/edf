<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost',
        'capacitor://localhost',
        'ionic://localhost',
        'http://192.168.1.134:8031',
        'http://192.168.31.117:8031',
        'https://192.168.31.117:8031',
        'https://192.168.1.134:8031',
        'https://website-7e5dc827.gtq.fvz.mybluehost.me',
        'https://web.edificiopacifik.com',
        'http://web.edificiopacifik.com',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
