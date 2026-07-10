<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function createUserForLogin(string $email = 'test@urbania.test', string $password = 'Secret1pass', string $estado = 'active'): User
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

// ---------------------------------------------------------------
// CASE 1: email + password correctos, user active → 200 + tokens
// ---------------------------------------------------------------
test('valid credentials return access token and refresh token cookie', function () {
    createUserForLogin();

    $response = postJson('/api/v1/auth/login', [
        'email' => 'test@urbania.test',
        'password' => 'Secret1pass',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', (int) config('jwt.ttl', 900));

    expect($response->headers->getCookies())->not->toBeEmpty();

    $cookie = $response->headers->getCookies()[0];
    expect($cookie->getName())->toBe('refresh_token');
    expect($cookie->isHttpOnly())->toBeTrue();
    expect($cookie->isSecure())->toBeFalse();
    expect($cookie->getSameSite())->toBe('strict');
    expect($cookie->getPath())->toBe('/api/v1/auth');

    $jwt = $response->json('access_token');
    $parts = explode('.', $jwt);
    expect($parts)->toHaveCount(3);

    $header = json_decode(base64_decode($parts[0]), true);
    expect($header['alg'])->toBe('RS256');
});

// ---------------------------------------------------------------
// CASE 2: email no existe → 401 INVALID_CREDENTIALS
// ---------------------------------------------------------------
test('non existent email returns 401 invalid credentials', function () {
    $response = postJson('/api/v1/auth/login', [
        'email' => 'noexiste@urbania.test',
        'password' => 'Secret1pass',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');
});

// ---------------------------------------------------------------
// CASE 3: email existe, password incorrecta → 401 INVALID_CREDENTIALS (mismo código que caso 2)
// ---------------------------------------------------------------
test('wrong password returns same error code as non existent email', function () {
    createUserForLogin();

    $response = postJson('/api/v1/auth/login', [
        'email' => 'test@urbania.test',
        'password' => 'WrongPassword1',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

    expect($response->json('error.code'))->toBe('INVALID_CREDENTIALS');

    $wrongEmailResponse = postJson('/api/v1/auth/login', [
        'email' => 'noexiste@urbania.test',
        'password' => 'Secret1pass',
    ]);

    expect($wrongEmailResponse->json('error.code'))->toBe('INVALID_CREDENTIALS');
    expect($response->json('error.code'))->toBe($wrongEmailResponse->json('error.code'));
});

// ---------------------------------------------------------------
// CASE 4: email existe, user suspended → 403 ACCOUNT_NOT_ACTIVE
// ---------------------------------------------------------------
test('suspended user returns 403 account not active', function () {
    createUserForLogin('suspended@urbania.test', 'Secret1pass', 'suspended');

    $response = postJson('/api/v1/auth/login', [
        'email' => 'suspended@urbania.test',
        'password' => 'Secret1pass',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('error.code', 'ACCOUNT_NOT_ACTIVE');
});

// ---------------------------------------------------------------
// CASE 5: payload sin email o password → 422 VALIDATION_ERROR
// ---------------------------------------------------------------
test('missing email returns 422 validation error', function () {
    $response = postJson('/api/v1/auth/login', [
        'password' => 'Secret1pass',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('missing password returns 422 validation error', function () {
    $response = postJson('/api/v1/auth/login', [
        'email' => 'test@urbania.test',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('invalid email format returns 422 validation error', function () {
    $response = postJson('/api/v1/auth/login', [
        'email' => 'not-an-email',
        'password' => 'Secret1pass',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

// ---------------------------------------------------------------
// CASE 6: throttle por muchos intentos → 429
// ---------------------------------------------------------------
test('rate limiting returns 429 after exceeding attempts', function () {
    createUserForLogin();

    for ($i = 0; $i < 5; $i++) {
        postJson('/api/v1/auth/login', [
            'email' => 'test@urbania.test',
            'password' => 'WrongPassword1',
        ]);
    }

    $response = postJson('/api/v1/auth/login', [
        'email' => 'test@urbania.test',
        'password' => 'Secret1pass',
    ]);

    $response->assertStatus(429);
});
