<?php

declare(strict_types=1);

namespace Urbania\Shared\JWT;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;

final readonly class JwtService
{
    private string $privateKey;

    private string $publicKey;

    private string $algorithm;

    private int $ttl;

    private int $refreshTtl;

    private string $issuer;

    public function __construct()
    {
        $this->privateKey = $this->loadKey('jwt.private_key', 'JWT private key not found');
        $this->publicKey = $this->loadKey('jwt.public_key', 'JWT public key not found');
        $this->algorithm = $this->configString('jwt.algorithm', 'RS256');
        $this->ttl = $this->configInt('jwt.ttl', 900);
        $this->refreshTtl = $this->configInt('jwt.refresh_ttl', 1209600);
        $this->issuer = $this->configString('jwt.issuer', 'http://localhost:8081');
    }

    /**
     * Issue an access token for a given subject.
     *
     * @param array<string, mixed> $claims Additional claims to embed in the token.
     *                                     Passed claims override defaults (including 'type' and 'exp').
     * @return string Signed JWT access token.
     */
    public function issueAccessToken(string $subject, array $claims = []): string
    {
        $now = time();

        $payload = array_merge([
            'iss' => $this->issuer,
            'sub' => $subject,
            'iat' => $now,
            'exp' => $now + $this->ttl,
            'type' => 'access',
        ], $claims);

        return JWT::encode($payload, $this->privateKey, $this->algorithm);
    }

    /**
     * Issue an MFA token for a given subject.
     *
     * MFA tokens have ultra-short TTL (5 min) and are only usable
     * for MFA verification endpoints — not as general access tokens.
     *
     * @return string Signed JWT mfa token.
     */
    public function issueMfaToken(string $subject): string
    {
        $now = time();

        $payload = [
            'iss' => $this->issuer,
            'sub' => $subject,
            'iat' => $now,
            'exp' => $now + 300,
            'type' => 'mfa',
            'mfa_verified' => false,
        ];

        return JWT::encode($payload, $this->privateKey, $this->algorithm);
    }

    /**
     * Issue a refresh token for a given subject.
     *
     * @return string Signed JWT refresh token.
     */
    public function issueRefreshToken(string $subject): string
    {
        $now = time();

        $payload = [
            'iss' => $this->issuer,
            'sub' => $subject,
            'iat' => $now,
            'exp' => $now + $this->refreshTtl,
            'type' => 'refresh',
            'jti' => bin2hex(random_bytes(16)),
        ];

        return JWT::encode($payload, $this->privateKey, $this->algorithm);
    }

    /**
     * Verify and decode a JWT token.
     *
     *
     * @throws ExpiredException If the token has expired.
     * @throws SignatureInvalidException If the signature is invalid.
     * @throws \UnexpectedValueException If the token is otherwise invalid.
     */
    public function verify(string $token): object
    {
        return JWT::decode($token, new Key($this->publicKey, $this->algorithm));
    }

    /**
     * Generate a test key pair (in-memory, no file I/O).
     * Useful for unit/integration tests.
     *
     * @return array{private: string, public: string}
     *
     * @throws \RuntimeException If key generation fails.
     */
    public static function generateTestKeyPair(): array
    {
        $configFile = __DIR__.'/../../../bin/openssl.cnf';

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $configFile,
        ]);

        if ($privateKey === false) {
            throw new \RuntimeException('Failed to generate test key pair: '.openssl_error_string());
        }

        $exported = openssl_pkey_export($privateKey, $privateKeyPem, null, ['config' => $configFile]);

        if ($exported === false) {
            throw new \RuntimeException('Failed to export test private key: '.openssl_error_string());
        }

        $keyDetails = openssl_pkey_get_details($privateKey);

        if ($keyDetails === false) {
            throw new \RuntimeException('Failed to extract test public key: '.openssl_error_string());
        }

        if (! is_string($privateKeyPem)) {
            throw new \RuntimeException('openssl_pkey_export failed to produce a string');
        }

        if (! isset($keyDetails['key']) || ! is_string($keyDetails['key'])) {
            throw new \RuntimeException('openssl_pkey_get_details did not return a valid key string');
        }

        return [
            'private' => $privateKeyPem,
            'public' => $keyDetails['key'],
        ];
    }

    /**
     * Load a key file and return its contents.
     *
     * @throws \RuntimeException If the file does not exist or is unreadable.
     */
    private function loadKey(string $configPath, string $errorMessage): string
    {
        $path = config($configPath);

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException("{$errorMessage}: config key '{$configPath}' is empty or missing");
        }

        if (! file_exists($path)) {
            throw new \RuntimeException("{$errorMessage}: {$path}");
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("{$errorMessage}: could not read {$path}");
        }

        return $contents;
    }

    /**
     * Get a string config value with validation and fallback.
     */
    private function configString(string $key, string $default): string
    {
        $value = config($key);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return $default;
    }

    /**
     * Get an int config value with validation and fallback.
     */
    private function configInt(string $key, int $default): int
    {
        $value = config($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        return $default;
    }
}
