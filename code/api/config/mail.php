<?php

declare(strict_types=1);

return [

    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'localhost'),
            'port' => env('MAIL_PORT', 1025),
            'encryption' => env('MAIL_ENCRYPTION'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'mailpit' => [
            'transport' => 'smtp',
            'host' => 'localhost',
            'port' => 1025,
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@urbania.test'),
        'name' => env('MAIL_FROM_NAME', 'Urbania'),
    ],

];
