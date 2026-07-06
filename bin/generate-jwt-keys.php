<?php

/**
 * Generate RS256 key pair for JWT signing.
 *
 * Uses bin/openssl.cnf explicitly (via the 'config' parameter) so that
 * key generation NEVER depends on the system's global openssl.cnf.
 * See api/API_ARCHITECTURE.md §10 for the rationale.
 */

declare(strict_types=1);

$configFile = __DIR__ . '/openssl.cnf';
$storageDir = __DIR__ . '/../storage/jwt';

if (! is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$privateKeyPath = $storageDir . '/private.pem';
$publicKeyPath = $storageDir . '/public.pem';

// --- Generate private key ---
$privateKey = openssl_pkey_new([
    'private_key_bits' => 4096,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'config' => $configFile,
]);

if ($privateKey === false) {
    fwrite(STDERR, '✗ Failed to generate private key: ' . openssl_error_string() . "\n");
    exit(1);
}

// --- Export private key ---
$exported = openssl_pkey_export($privateKey, $privateKeyPem, null, ['config' => $configFile]);

if ($exported === false) {
    fwrite(STDERR, '✗ Failed to export private key: ' . openssl_error_string() . "\n");
    exit(1);
}

file_put_contents($privateKeyPath, $privateKeyPem);
chmod($privateKeyPath, 0600);

// --- Extract public key ---
$keyDetails = openssl_pkey_get_details($privateKey);

if ($keyDetails === false) {
    fwrite(STDERR, '✗ Failed to extract public key: ' . openssl_error_string() . "\n");
    exit(1);
}

file_put_contents($publicKeyPath, $keyDetails['key']);
chmod($publicKeyPath, 0644);

echo "✓ JWT keys generated in storage/jwt/\n";
echo "  - private.pem (NO commitear — ya está en .gitignore)\n";
echo "  - public.pem  (puede commitearse)\n";

// --- Verify: sign + verify a test payload ---
$testPayload = json_encode([
    'sub' => 'test',
    'iat' => time(),
    'exp' => time() + 60,
]);

$signature = '';
$signOk = openssl_sign($testPayload, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);

if (! $signOk) {
    fwrite(STDERR, '✗ Verification failed: could not sign test payload' . "\n");
    exit(1);
}

$verifyOk = openssl_verify($testPayload, $signature, $keyDetails['key'], OPENSSL_ALGO_SHA256);

if ($verifyOk === 1) {
    echo "✓ Verificación RS256: OK — las llaves funcionan correctamente.\n";
} else {
    fwrite(STDERR, '✗ Verification failed: RS256 sign/verify mismatch' . "\n");
    exit(1);
}

exit(0);
