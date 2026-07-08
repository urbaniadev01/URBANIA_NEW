<?php

declare(strict_types=1);
use Urbania\Shared\JWT\JwtService;

test('Pest smoke test', function () {
    expect(true)->toBeTrue();
});

test('JWT test key pair generation works', function () {
    $pair = JwtService::generateTestKeyPair();

    expect($pair)->toHaveKeys(['private', 'public']);
    expect($pair['private'])->toStartWith("-----BEGIN PRIVATE KEY-----\n");
    expect($pair['public'])->toStartWith("-----BEGIN PUBLIC KEY-----\n");
});
