<?php

declare(strict_types=1);

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Auth\Infrastructure\Models\EloquentRefreshToken;
use Urbania\Shared\JWT\JwtService;

use function Pest\Laravel\call;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

function createUserForRefresh(string $email = 'test@urbania.test', string $password = 'Secret1pass', string $estado = 'active'): User
{
    $org = new EloquentOrganization(['nombre' => 'Urbania Test']);
    $org->save();

    $user = new User([
        'organization_id' => $org->id,
        'email' => $email,
        'password_hash' => Hash::make($password),
        'estado' => $estado,
    ]);
    $user->save();

    return $user;
}

/**
 * Issue a refresh token by calling JwtService directly (simulates login).
 */
function issueRefreshTokenForUser(User $user): string
{
    $jwt = app(JwtService::class);

    return $jwt->issueRefreshToken((string) $user->id);
}

/**
 * Decode a JWT without verification (for extracting claims in tests).
 */
function decodeJwt(string $token): object
{
    $parts = explode('.', $token);

    return json_decode(base64_decode($parts[1]), false);
}

// ---------------------------------------------------------------
// CASE 1: refresh_token valido y vigente -> 200 + nuevo par
// ---------------------------------------------------------------
test('valid refresh token returns new access token and refresh token cookie', function () {
    $user = createUserForRefresh();
    $refreshToken = issueRefreshTokenForUser($user);

    $response = call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => $refreshToken]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', (int) config('jwt.ttl', 900));

    // Verify cookie is set with correct flags
    $cookies = $response->headers->getCookies();
    expect($cookies)->not->toBeEmpty();

    $cookie = $cookies[0];
    expect($cookie->getName())->toBe('refresh_token');
    expect($cookie->isHttpOnly())->toBeTrue();
    expect($cookie->isSecure())->toBeTrue();
    expect($cookie->getSameSite())->toBe('strict');
    expect($cookie->getPath())->toBe('/api/v1/auth');

    // Verify access token is a valid RS256 JWT
    $jwt = $response->json('access_token');
    $parts = explode('.', $jwt);
    expect($parts)->toHaveCount(3);

    $header = json_decode(base64_decode($parts[0]), true);
    expect($header['alg'])->toBe('RS256');

    // Verify new refresh token is different from the one sent
    $newRefreshToken = $cookie->getValue();
    expect($newRefreshToken)->not->toBe($refreshToken);

    // Verify old token was registered in DB as valido
    $oldJti = decodeJwt($refreshToken)->jti;
    $oldRecord = EloquentRefreshToken::where('jti', $oldJti)->first();
    expect($oldRecord)->not->toBeNull();
    expect($oldRecord->estado)->toBe('valido');

    // Verify new token was also persisted
    $newJti = decodeJwt($newRefreshToken)->jti;
    $newRecord = EloquentRefreshToken::where('jti', $newJti)->first();
    expect($newRecord)->not->toBeNull();
    expect($newRecord->estado)->toBe('valido');
});

// ---------------------------------------------------------------
// CASE 2: sin cookie refresh_token -> 401 REFRESH_TOKEN_MISSING
// ---------------------------------------------------------------
test('missing refresh token cookie returns 401 refresh token missing', function () {
    $response = postJson('/api/v1/auth/refresh');

    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'REFRESH_TOKEN_MISSING');
});

// ---------------------------------------------------------------
// CASE 3: refresh_token expirado -> 401 REFRESH_TOKEN_EXPIRED
// ---------------------------------------------------------------
test('expired refresh token returns 401 refresh token expired', function () {
    $user = createUserForRefresh();

    // Use the app's own private key so that the signature is valid
    $privateKeyPath = config('jwt.private_key');
    if (! is_string($privateKeyPath) || ! file_exists($privateKeyPath)) {
        skip('JWT private key not found for testing.');
    }
    $privateKey = file_get_contents($privateKeyPath);

    // Generate an already-expired refresh token
    $now = time();
    $payload = [
        'iss' => config('jwt.issuer', 'http://localhost:8081'),
        'sub' => (string) $user->id,
        'iat' => $now - 7200,
        'exp' => $now - 3600, // expired 1 hour ago
        'type' => 'refresh',
        'jti' => bin2hex(random_bytes(16)),
    ];

    $expiredToken = JWT::encode($payload, $privateKey, 'RS256');

    $response = call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => $expiredToken]);

    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'REFRESH_TOKEN_EXPIRED');
});

// ---------------------------------------------------------------
// CASE 4: refresh_token ya usado (reuso) -> 401 REFRESH_TOKEN_REUSED + revocacion masiva
// ---------------------------------------------------------------
test('reused refresh token returns 401 refresh token reused and triggers mass revocation', function () {
    $user = createUserForRefresh();
    $refreshToken = issueRefreshTokenForUser($user);
    $oldJti = decodeJwt($refreshToken)->jti;

    // First refresh: creates record as valido, issues new pair
    $firstResponse = call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => $refreshToken]);
    $firstResponse->assertOk();

    // Verify old token is now valido
    $record = EloquentRefreshToken::where('jti', $oldJti)->first();
    expect($record)->not->toBeNull();
    expect($record->estado)->toBe('valido');

    // Second refresh with SAME token: normal rotation, marks as invalidado
    $secondResponse = call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => $refreshToken]);
    $secondResponse->assertOk();

    $record->refresh();
    expect($record->estado)->toBe('invalidado');

    // Third refresh with SAME token: REUSE detected -> mass revocation
    $thirdResponse = call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => $refreshToken]);

    $thirdResponse->assertUnauthorized()
        ->assertJsonPath('error.code', 'REFRESH_TOKEN_REUSED');
});

// ---------------------------------------------------------------
// CASE 5: tras caso 4, otro token del mismo user -> 401 (confirma revocacion masiva)
// ---------------------------------------------------------------
test('after reuse detection another valid token from same user also fails', function () {
    $user = createUserForRefresh();
    $refreshTokenA = issueRefreshTokenForUser($user);
    $refreshTokenB = issueRefreshTokenForUser($user);
    $jtiB = decodeJwt($refreshTokenB)->jti;

    // Register token B in DB as valido (simulating a previous refresh that persisted it)
    EloquentRefreshToken::create([
        'user_id' => $user->id,
        'jti' => $jtiB,
        'estado' => 'valido',
        'expires_at' => date('c', time() + 1209600),
    ]);

    // First refresh with token A: creates valido record
    call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => $refreshTokenA])->assertOk();

    // Second refresh with token A: normal rotation -> marks invalidado
    call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => $refreshTokenA])->assertOk();

    // Third refresh with token A: REUSE -> mass revocation
    call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => $refreshTokenA])
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'REFRESH_TOKEN_REUSED');

    // Now try token B (which was valido before mass revocation)
    $response = call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => $refreshTokenB]);

    // Token B should now be invalidado (mass revocation affected it)
    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'REFRESH_TOKEN_REUSED');
});

// ---------------------------------------------------------------
// Additional: access token JWT type triggers missing error
// ---------------------------------------------------------------
test('access token used as refresh token returns 401 refresh token missing', function () {
    $user = createUserForRefresh();
    $jwtService = app(JwtService::class);
    $accessToken = $jwtService->issueAccessToken((string) $user->id);

    $response = call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => $accessToken]);

    // Should fail because token type is 'access', not 'refresh'
    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'REFRESH_TOKEN_MISSING');
});

// ---------------------------------------------------------------
// Additional: suspended user cannot refresh
// ---------------------------------------------------------------
test('suspended user refresh token returns 403 account not active', function () {
    $user = createUserForRefresh('suspended@urbania.test', 'Secret1pass', 'suspended');
    $refreshToken = issueRefreshTokenForUser($user);

    $response = call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => $refreshToken]);

    $response->assertForbidden()
        ->assertJsonPath('error.code', 'ACCOUNT_NOT_ACTIVE');
});

// ---------------------------------------------------------------
// Additional: rate limiting
// ---------------------------------------------------------------
test('refresh rate limiting returns 429 after exceeding attempts', function () {
    for ($i = 0; $i < 10; $i++) {
        call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => 'invalid_token_'.$i]);
    }

    $response = call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => 'one_more']);

    $response->assertStatus(429);
});
