<?php

declare(strict_types=1);

test('Pest smoke test', function () {
    expect(true)->toBeTrue();
});

test('JWT test key pair generation works', function () {
    $pair = \Urbania\Shared\JWT\JwtService::generateTestKeyPair();

    expect($pair)->toHaveKeys(['private', 'public']);
    expect($pair['private'])->toStartWith("-----BEGIN PRIVATE KEY-----\n");
    expect($pair['public'])->toStartWith("-----BEGIN PUBLIC KEY-----\n");
});
