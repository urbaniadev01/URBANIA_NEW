<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Private Key
    |--------------------------------------------------------------------------
    | Path to the RS256 private key file (PEM format).
    | This key MUST NOT be committed to version control.
    */

    'private_key' => env('JWT_PRIVATE_KEY', storage_path('jwt/private.pem')),

    /*
    |--------------------------------------------------------------------------
    | JWT Public Key
    |--------------------------------------------------------------------------
    | Path to the RS256 public key file (PEM format).
    | This key CAN be committed — it verifies tokens but cannot sign them.
    */

    'public_key' => env('JWT_PUBLIC_KEY', storage_path('jwt/public.pem')),

    /*
    |--------------------------------------------------------------------------
    | JWT Algorithm
    |--------------------------------------------------------------------------
    | Must be RS256 — asymmetric, never HS256.
    */

    'algorithm' => env('JWT_ALGORITHM', 'RS256'),

    /*
    |--------------------------------------------------------------------------
    | Access Token TTL (seconds)
    |--------------------------------------------------------------------------
    | Short-lived: 15 minutes by default.
    */

    'ttl' => (int) env('JWT_TTL', 900),

    /*
    |--------------------------------------------------------------------------
    | Refresh Token TTL (seconds)
    |--------------------------------------------------------------------------
    | Long-lived: 14 days by default, with rotation.
    */

    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 1209600),

    /*
    |--------------------------------------------------------------------------
    | Issuer
    |--------------------------------------------------------------------------
    */

    'issuer' => env('APP_URL', 'http://localhost:8081'),

    /*
    |--------------------------------------------------------------------------
    | Leeway (seconds)
    |--------------------------------------------------------------------------
    | Clock skew tolerance.
    */

    'leeway' => 0,

];
