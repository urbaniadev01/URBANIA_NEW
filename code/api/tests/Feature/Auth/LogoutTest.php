<?php

declare(strict_types=1);

use App\Models\User;
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

function createUserForLogout(string $email = 'test@urbania.test', string $password = 'Secret1pass', string $estado = 'active'): User
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

function issueRefreshTokenForLogout(User $user): string
{
    $jwt = app(JwtService::class);

    return $jwt->issueRefreshToken((string) $user->id);
}

function decodeJwtForLogout(string $token): object
{
    $parts = explode('.', $token);

    return json_decode(base64_decode($parts[1]), false);
}

// ---------------------------------------------------------------
// CASE 1: refresh_token válido → 200, token revocado, cookie limpiada
// ---------------------------------------------------------------
test('valid refresh token is revoked and cookie is cleared', function () {
    $user = createUserForLogout();
    $refreshToken = issueRefreshTokenForLogout($user);
    $jti = decodeJwtForLogout($refreshToken)->jti;

    // Persist the token in DB as valido (simulating a prior login/refresh)
    EloquentRefreshToken::create([
        'user_id' => $user->id,
        'jti' => $jti,
        'estado' => 'valido',
        'expires_at' => date('c', time() + 1209600),
    ]);

    $response = call('POST', '/api/v1/auth/logout', [], ['refresh_token' => $refreshToken]);

    // Response is 200
    $response->assertOk()
        ->assertJsonPath('message', 'Sesión cerrada exitosamente.');

    // Verify cookie is cleared (empty value, expired)
    $cookies = $response->headers->getCookies();
    expect($cookies)->not->toBeEmpty();

    $cookie = $cookies[0];
    expect($cookie->getName())->toBe('refresh_token');
    expect($cookie->getValue())->toBe('');
    expect((int) $cookie->getExpiresTime())->toBeLessThan(time());
    expect($cookie->isHttpOnly())->toBeTrue();
    expect($cookie->isSecure())->toBeFalse();
    expect($cookie->getSameSite())->toBe('strict');
    expect($cookie->getPath())->toBe('/api/v1/auth');

    // Verify token is marked as invalidado in DB
    $record = EloquentRefreshToken::where('jti', $jti)->first();
    expect($record)->not->toBeNull();
    expect($record->estado)->toBe('invalidado');
});

// ---------------------------------------------------------------
// CASE 2: sin refresh_token → 200 (idempotente)
// ---------------------------------------------------------------
test('logout without refresh token cookie returns 200 idempotent', function () {
    $response = postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJsonPath('message', 'Sesión cerrada exitosamente.');

    // Verify cookie is cleared even without a token
    $cookies = $response->headers->getCookies();
    expect($cookies)->not->toBeEmpty();

    $cookie = $cookies[0];
    expect($cookie->getName())->toBe('refresh_token');
    expect($cookie->getValue())->toBe('');
    expect((int) $cookie->getExpiresTime())->toBeLessThan(time());
});

// ---------------------------------------------------------------
// CASE 3: tras caso 1, usar el token revocado en /auth/refresh → 401
// ---------------------------------------------------------------
test('revoked refresh token fails on subsequent refresh', function () {
    $user = createUserForLogout();
    $refreshToken = issueRefreshTokenForLogout($user);
    $jti = decodeJwtForLogout($refreshToken)->jti;

    // Persist the token in DB as valido
    EloquentRefreshToken::create([
        'user_id' => $user->id,
        'jti' => $jti,
        'estado' => 'valido',
        'expires_at' => date('c', time() + 1209600),
    ]);

    // Step 1: Logout → revokes the token
    $logoutResponse = call('POST', '/api/v1/auth/logout', [], ['refresh_token' => $refreshToken]);
    $logoutResponse->assertOk();

    // Confirm token is invalidado
    $record = EloquentRefreshToken::where('jti', $jti)->first();
    expect($record)->not->toBeNull();
    expect($record->estado)->toBe('invalidado');

    // Step 2: Try to use the same token for refresh → should fail
    $refreshResponse = call('POST', '/api/v1/auth/refresh', [], ['refresh_token' => $refreshToken]);

    $refreshResponse->assertUnauthorized()
        ->assertJsonPath('error.code', 'REFRESH_TOKEN_REUSED');
});

// ---------------------------------------------------------------
// Additional: idempotent with expired token
// ---------------------------------------------------------------
test('logout with expired refresh token returns 200 idempotent', function () {
    $user = createUserForLogout();
    $refreshToken = issueRefreshTokenForLogout($user);
    $jti = decodeJwtForLogout($refreshToken)->jti;

    // Persist as valido but the JWT itself is still valid
    EloquentRefreshToken::create([
        'user_id' => $user->id,
        'jti' => $jti,
        'estado' => 'valido',
        'expires_at' => date('c', time() - 3600), // already expired in DB
    ]);

    $response = call('POST', '/api/v1/auth/logout', [], ['refresh_token' => $refreshToken]);

    $response->assertOk()
        ->assertJsonPath('message', 'Sesión cerrada exitosamente.');
});

// ---------------------------------------------------------------
// Additional: idempotent with already invalidated token
// ---------------------------------------------------------------
test('logout with already invalidated token returns 200 idempotent', function () {
    $user = createUserForLogout();
    $refreshToken = issueRefreshTokenForLogout($user);
    $jti = decodeJwtForLogout($refreshToken)->jti;

    // Token already invalidado
    EloquentRefreshToken::create([
        'user_id' => $user->id,
        'jti' => $jti,
        'estado' => 'invalidado',
        'expires_at' => date('c', time() + 1209600),
    ]);

    $response = call('POST', '/api/v1/auth/logout', [], ['refresh_token' => $refreshToken]);

    $response->assertOk()
        ->assertJsonPath('message', 'Sesión cerrada exitosamente.');
});

// ---------------------------------------------------------------
// Additional: rate limiting
// ---------------------------------------------------------------
test('logout rate limiting returns 429 after exceeding attempts', function () {
    for ($i = 0; $i < 10; $i++) {
        call('POST', '/api/v1/auth/logout');
    }

    $response = call('POST', '/api/v1/auth/logout');

    $response->assertStatus(429);
});
