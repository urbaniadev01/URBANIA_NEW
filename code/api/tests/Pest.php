<?php

declare(strict_types=1);

use Tests\TestCase;
use Urbania\Shared\JWT\JwtService;

uses(TestCase::class)->in('Feature', 'Integration', 'Security', 'Unit');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * Helper: get the test JWT private key (in-memory, no file I/O).
 */
function testJwtPrivateKey(): string
{
    $pair = JwtService::generateTestKeyPair();

    return $pair['private'];
}

/**
 * Helper: get the test JWT public key (in-memory, no file I/O).
 */
function testJwtPublicKey(): string
{
    $pair = JwtService::generateTestKeyPair();

    return $pair['public'];
}
