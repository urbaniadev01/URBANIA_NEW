<?php

uses(Tests\TestCase::class)->in('Feature', 'Integration', 'Security');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * Helper: get the test JWT private key (in-memory, no file I/O).
 */
function testJwtPrivateKey(): string
{
    $pair = \Urbania\Shared\JWT\JwtService::generateTestKeyPair();
    return $pair['private'];
}

/**
 * Helper: get the test JWT public key (in-memory, no file I/O).
 */
function testJwtPublicKey(): string
{
    $pair = \Urbania\Shared\JWT\JwtService::generateTestKeyPair();
    return $pair['public'];
}
