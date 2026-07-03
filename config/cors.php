<?php

return [

  'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

  'allowed_methods' => ['*'],

  'allowed_origins' => ['*', 'http://192.168.1.27:8031', 'http://192.168.31.117:8031', 'https://website-7e5dc827.gtq.fvz.mybluehost.me'],
  'allowed_origins_patterns' => ['*'],
  'allowed_headers' => ['*'],
  'exposed_headers' => [],
  'max_age' => 0,
  'supports_credentials' => true,
];
