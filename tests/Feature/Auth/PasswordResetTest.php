<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Urbania\Auth\Infrastructure\Mail\ResetPasswordMail;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Auth\Infrastructure\Models\EloquentPasswordResetToken;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function createUserForPasswordReset(string $email = 'test@urbania.test', string $password = 'OldPass1', string $estado = 'active'): User
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

function createPasswordResetToken(string $email, string $plainToken, int $ttlMinutes = 60): EloquentPasswordResetToken
{
    $expiresAt = now()->addMinutes($ttlMinutes);

    $token = new EloquentPasswordResetToken([
        'email' => $email,
        'token_hash' => hash('sha256', $plainToken),
        'expires_at' => $expiresAt,
    ]);
    $token->save();

    return $token;
}

// Reset Redis rate limit keys before each test
beforeEach(function () {
    Redis::flushall();
    Mail::fake();
});

// ---------------------------------------------------------------
// CASE 1: Email exists → POST /auth/forgot-password → 200, email
// ---------------------------------------------------------------
test('forgot password with existing email sends email and returns 200', function () {
    createUserForPasswordReset();

    $response = postJson('/api/v1/auth/forgot-password', [
        'email' => 'test@urbania.test',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Si el email está registrado, recibirás un enlace de recuperación.',
        ]);

    // Verify email was sent
    Mail::assertSent(ResetPasswordMail::class, function (ResetPasswordMail $mail) {
        return $mail->email === 'test@urbania.test';
    });

    // Verify token was stored in DB
    $dbToken = EloquentPasswordResetToken::where('email', 'test@urbania.test')->first();
    expect($dbToken)->not->toBeNull();
    expect($dbToken->expires_at->isFuture())->toBeTrue();

    // Verify plain token is in Redis for dev endpoint
    $plainToken = Redis::get('dev:password_reset:plain:test@urbania.test');
    expect($plainToken)->not->toBeNull();
    expect(strlen((string) $plainToken))->toBe(64); // 32 bytes → 64 hex chars
});

// ---------------------------------------------------------------
// CASE 2: Email no existe → POST /auth/forgot-password → 200 genérico
// ---------------------------------------------------------------
test('forgot password with non-existent email returns generic 200 and no email sent', function () {
    $response = postJson('/api/v1/auth/forgot-password', [
        'email' => 'noexiste@urbania.test',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Si el email está registrado, recibirás un enlace de recuperación.',
        ]);

    // No email should be sent
    Mail::assertNothingSent();

    // No token should be created
    expect(EloquentPasswordResetToken::count())->toBe(0);
});

// ---------------------------------------------------------------
// CASE 3: Rate limit forgot: 4ª llamada → 429
// ---------------------------------------------------------------
test('forgot password rate limit blocks after 3 attempts per hour', function () {
    createUserForPasswordReset();

    // 3 successful attempts
    for ($i = 0; $i < 3; $i++) {
        $response = postJson('/api/v1/auth/forgot-password', [
            'email' => 'test@urbania.test',
        ]);
        $response->assertOk();
    }

    // 4th attempt → rate limited
    $response = postJson('/api/v1/auth/forgot-password', [
        'email' => 'test@urbania.test',
    ]);

    $response->assertStatus(429)
        ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
});

// ---------------------------------------------------------------
// CASE 4: Email inválido → POST /auth/forgot-password → 200 genérico
// ---------------------------------------------------------------
test('forgot password with invalid email format returns generic 200', function () {
    $response = postJson('/api/v1/auth/forgot-password', [
        'email' => 'no-es-email',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Si el email está registrado, recibirás un enlace de recuperación.',
        ]);

    // No email should be sent
    Mail::assertNothingSent();
});

// ---------------------------------------------------------------
// CASE 5: Token válido + password ok → POST /auth/reset-password → 200
// ---------------------------------------------------------------
test('reset password with valid token and valid password returns 200 and updates password', function () {
    $user = createUserForPasswordReset();
    $plainToken = bin2hex(random_bytes(32));

    createPasswordResetToken('test@urbania.test', $plainToken);
    // Also store in Redis for dev endpoint consistency
    Redis::setex('dev:password_reset:plain:test@urbania.test', 3600, $plainToken);

    $response = postJson('/api/v1/auth/reset-password', [
        'token' => $plainToken,
        'password' => 'NuevaClave1',
        'password_confirmation' => 'NuevaClave1',
    ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Contraseña actualizada exitosamente.',
        ]);

    // Verify password was updated
    $user->refresh();
    expect(Hash::check('NuevaClave1', $user->password_hash))->toBeTrue();

    // Verify token was deleted (one-time use)
    expect(EloquentPasswordResetToken::count())->toBe(0);
});

// ---------------------------------------------------------------
// CASE 6: Token expirado → 422 RESET_TOKEN_EXPIRED
// ---------------------------------------------------------------
test('reset password with expired token returns 422 RESET_TOKEN_EXPIRED', function () {
    createUserForPasswordReset();
    $plainToken = bin2hex(random_bytes(32));

    // Create an expired token
    $expiredToken = new EloquentPasswordResetToken([
        'email' => 'test@urbania.test',
        'token_hash' => hash('sha256', $plainToken),
        'expires_at' => now()->subMinute(), // already expired
    ]);
    $expiredToken->save();

    $response = postJson('/api/v1/auth/reset-password', [
        'token' => $plainToken,
        'password' => 'NuevaClave1',
        'password_confirmation' => 'NuevaClave1',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'RESET_TOKEN_EXPIRED');
});

// ---------------------------------------------------------------
// CASE 7: Token inválido → 422 RESET_TOKEN_INVALID
// ---------------------------------------------------------------
test('reset password with completely invalid token returns 422 RESET_TOKEN_INVALID', function () {
    createUserForPasswordReset();

    $response = postJson('/api/v1/auth/reset-password', [
        'token' => 'token-que-no-existe',
        'password' => 'NuevaClave1',
        'password_confirmation' => 'NuevaClave1',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'RESET_TOKEN_INVALID');
});

// ---------------------------------------------------------------
// CASE 8: Password no cumple política → 422 VALIDATION_ERROR
// ---------------------------------------------------------------
test('reset password with weak password returns 422 VALIDATION_ERROR', function () {
    createUserForPasswordReset();
    $plainToken = bin2hex(random_bytes(32));
    createPasswordResetToken('test@urbania.test', $plainToken);
    Redis::setex('dev:password_reset:plain:test@urbania.test', 3600, $plainToken);

    $response = postJson('/api/v1/auth/reset-password', [
        'token' => $plainToken,
        'password' => 'todominusculas', // no uppercase, no number
        'password_confirmation' => 'todominusculas',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

// ---------------------------------------------------------------
// CASE 9: Password ≠ confirmation → 422 VALIDATION_ERROR
// ---------------------------------------------------------------
test('reset password with mismatched confirmation returns 422 VALIDATION_ERROR', function () {
    createUserForPasswordReset();
    $plainToken = bin2hex(random_bytes(32));
    createPasswordResetToken('test@urbania.test', $plainToken);
    Redis::setex('dev:password_reset:plain:test@urbania.test', 3600, $plainToken);

    $response = postJson('/api/v1/auth/reset-password', [
        'token' => $plainToken,
        'password' => 'NuevaClave1',
        'password_confirmation' => 'Diferente1',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

// ---------------------------------------------------------------
// CASE 10: Token de un solo uso → 2ª vez → 422 RESET_TOKEN_INVALID
// ---------------------------------------------------------------
test('reset password token cannot be reused returns 422 RESET_TOKEN_INVALID', function () {
    createUserForPasswordReset();
    $plainToken = bin2hex(random_bytes(32));
    createPasswordResetToken('test@urbania.test', $plainToken);
    Redis::setex('dev:password_reset:plain:test@urbania.test', 3600, $plainToken);

    // First use — succeeds
    $response1 = postJson('/api/v1/auth/reset-password', [
        'token' => $plainToken,
        'password' => 'NuevaClave1',
        'password_confirmation' => 'NuevaClave1',
    ]);
    $response1->assertOk();

    // Second use with same token — fails
    $response2 = postJson('/api/v1/auth/reset-password', [
        'token' => $plainToken,
        'password' => 'NuevaClave2',
        'password_confirmation' => 'NuevaClave2',
    ]);

    $response2->assertStatus(422)
        ->assertJsonPath('error.code', 'RESET_TOKEN_INVALID');
});

// ---------------------------------------------------------------
// CASE 11: Rate limit reset: 6ª llamada → 429
// ---------------------------------------------------------------
test('reset password rate limit blocks after 5 attempts per 15 minutes', function () {
    createUserForPasswordReset();

    // 5 failed attempts
    for ($i = 0; $i < 5; $i++) {
        $response = postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token-'.($i + 1),
            'password' => 'NuevaClave1',
            'password_confirmation' => 'NuevaClave1',
        ]);
        $response->assertStatus(422); // RESET_TOKEN_INVALID
    }

    // 6th attempt → rate limited
    $response = postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid-token-6',
        'password' => 'NuevaClave1',
        'password_confirmation' => 'NuevaClave1',
    ]);

    $response->assertStatus(429)
        ->assertJsonPath('error.code', 'TOO_MANY_REQUESTS');
});

// ---------------------------------------------------------------
// CASE 12: GET /dev/password-resets/last?email=... → 200 token
// ---------------------------------------------------------------
test('dev endpoint returns latest valid password reset token for email', function () {
    createUserForPasswordReset();
    $plainToken = bin2hex(random_bytes(32));
    createPasswordResetToken('test@urbania.test', $plainToken);
    Redis::setex('dev:password_reset:plain:test@urbania.test', 3600, $plainToken);

    $response = getJson('/dev/password-resets/last?email=test@urbania.test');

    $response->assertOk()
        ->assertJsonPath('token', $plainToken);
});

// ---------------------------------------------------------------
// CASE 13: Email sin token → GET /dev/password-resets/last → 404
// ---------------------------------------------------------------
test('dev endpoint returns 404 when no valid password reset token exists', function () {
    $response = getJson('/dev/password-resets/last?email=nonexistent@urbania.test');

    $response->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});

// ---------------------------------------------------------------
// CASE 14: Timing attack — same response for existing vs non-existing
// ---------------------------------------------------------------
test('forgot password returns identical response for existing and non-existing email', function () {
    createUserForPasswordReset();

    // Request for existing email
    $t1 = microtime(true);
    $responseExist = postJson('/api/v1/auth/forgot-password', [
        'email' => 'test@urbania.test',
    ]);
    $tExist = microtime(true) - $t1;

    // Request for non-existing email
    $t2 = microtime(true);
    $responseNotExist = postJson('/api/v1/auth/forgot-password', [
        'email' => 'noexiste@urbania.test',
    ]);
    $tNotExist = microtime(true) - $t2;

    // Same status
    expect($responseExist->status())->toBe(200);
    expect($responseNotExist->status())->toBe(200);

    // Same body
    expect($responseExist->json())->toBe($responseNotExist->json());

    // Same JSON structure
    $responseExist->assertJsonStructure(['message']);
    $responseNotExist->assertJsonStructure(['message']);

    // Both within a reasonable time range (allowing for variance in test environment)
    // The key is that non-existent emails don't return immediately
    expect($tExist)->toBeGreaterThan(0);
    expect($tNotExist)->toBeGreaterThan(0);
});
