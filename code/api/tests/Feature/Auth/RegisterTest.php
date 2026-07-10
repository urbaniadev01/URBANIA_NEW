<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Urbania\Auth\Infrastructure\Models\EloquentInvitation;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------
// Helper: create an organization and return it
// ---------------------------------------------------------------
function createOrganization(): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => 'Urbania Test']);
    $org->save();

    return $org;
}

// ---------------------------------------------------------------
// Helper: create a valid (vigente, not expired) invitation
// ---------------------------------------------------------------
function createValidInvitation(string $email = 'test@urbania.test'): EloquentInvitation
{
    $org = createOrganization();

    $invitation = new EloquentInvitation([
        'organization_id' => $org->id,
        'email' => $email,
        'token' => bin2hex(random_bytes(32)),
        'estado' => 'vigente',
        'expira_en' => now()->addDays(7),
    ]);
    $invitation->save();

    return $invitation;
}

// ---------------------------------------------------------------
// CASE 1: Valid invitation → 201, user + contact created, invitation consumed
// ---------------------------------------------------------------
test('valid invitation creates user and contact, marks invitation consumed', function () {
    $invitation = createValidInvitation();

    $response = postJson('/api/v1/auth/register', [
        'invitation_token' => $invitation->token,
        'password' => 'Secret1pass',
        'name' => 'Juan Pérez',
        'phone' => '+573001234567',
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Registro exitoso')
        ->assertJsonPath('user.email', 'test@urbania.test')
        ->assertJsonPath('user.name', 'Juan Pérez')
        ->assertJsonPath('user.estado', 'active')
        ->assertJsonPath('user.organization_id', $invitation->organization_id);

    // Verify user exists in DB
    $this->assertDatabaseHas('users', [
        'email' => 'test@urbania.test',
        'estado' => 'active',
        'organization_id' => $invitation->organization_id,
    ]);

    // Verify contact exists in DB (invariant: user must have a contact)
    $this->assertDatabaseHas('contacts', [
        'email' => 'test@urbania.test',
        'nombre' => 'Juan Pérez',
        'telefono' => '+573001234567',
    ]);

    // Verify invitation is consumed
    $this->assertDatabaseHas('invitations', [
        'id' => $invitation->id,
        'estado' => 'consumida',
    ]);

    // Verify password is hashed
    $user = User::where('email', 'test@urbania.test')->first();
    expect(Hash::check('Secret1pass', $user->password_hash))->toBeTrue();
});

// ---------------------------------------------------------------
// CASE 2: No invitation_token → 422
// ---------------------------------------------------------------
test('missing invitation token returns 422', function () {
    $response = postJson('/api/v1/auth/register', [
        'password' => 'Secret1pass',
        'name' => 'Juan Pérez',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

// ---------------------------------------------------------------
// CASE 3: Non-existent token → 403
// ---------------------------------------------------------------
test('non existent invitation token returns 403', function () {
    $response = postJson('/api/v1/auth/register', [
        'invitation_token' => 'fake-token-that-does-not-exist',
        'password' => 'Secret1pass',
        'name' => 'Juan Pérez',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('error.code', 'INVITATION_TOKEN_INVALID');
});

// ---------------------------------------------------------------
// CASE 4: Already consumed token → 403
// ---------------------------------------------------------------
test('already consumed invitation token returns 403', function () {
    $invitation = createValidInvitation();
    $invitation->update(['estado' => 'consumida']);

    $response = postJson('/api/v1/auth/register', [
        'invitation_token' => $invitation->token,
        'password' => 'Secret1pass',
        'name' => 'Juan Pérez',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('error.code', 'INVITATION_TOKEN_INVALID');
});

// ---------------------------------------------------------------
// CASE 5: Expired token → 403
// ---------------------------------------------------------------
test('expired invitation token returns 403', function () {
    $invitation = createValidInvitation();
    $invitation->update(['expira_en' => now()->subDay()]);

    $response = postJson('/api/v1/auth/register', [
        'invitation_token' => $invitation->token,
        'password' => 'Secret1pass',
        'name' => 'Juan Pérez',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('error.code', 'INVITATION_TOKEN_INVALID');
});

// ---------------------------------------------------------------
// CASE 6: Email already registered → 409
// ---------------------------------------------------------------
test('email already registered returns 409', function () {
    $invitation = createValidInvitation('duplicated@urbania.test');

    // First registration succeeds
    postJson('/api/v1/auth/register', [
        'invitation_token' => $invitation->token,
        'password' => 'Secret1pass',
        'name' => 'Juan Pérez',
    ])->assertCreated();

    // Create a new invitation for the same email
    $invitation2 = createValidInvitation('duplicated@urbania.test');

    // Second registration with same email fails
    $response = postJson('/api/v1/auth/register', [
        'invitation_token' => $invitation2->token,
        'password' => 'Another1pass',
        'name' => 'Juan Pérez',
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'EMAIL_ALREADY_REGISTERED');
});

// ---------------------------------------------------------------
// CASE 7: Weak password → 422
// ---------------------------------------------------------------
test('weak password returns 422', function () {
    $invitation = createValidInvitation();

    // Too short
    $response = postJson('/api/v1/auth/register', [
        'invitation_token' => $invitation->token,
        'password' => 'Ab1',
        'name' => 'Juan Pérez',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('password without uppercase returns 422', function () {
    $invitation = createValidInvitation();

    $response = postJson('/api/v1/auth/register', [
        'invitation_token' => $invitation->token,
        'password' => 'secret1pass',
        'name' => 'Juan Pérez',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('password without lowercase returns 422', function () {
    $invitation = createValidInvitation();

    $response = postJson('/api/v1/auth/register', [
        'invitation_token' => $invitation->token,
        'password' => 'SECRET1PASS',
        'name' => 'Juan Pérez',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

test('password without number returns 422', function () {
    $invitation = createValidInvitation();

    $response = postJson('/api/v1/auth/register', [
        'invitation_token' => $invitation->token,
        'password' => 'SecretPass',
        'name' => 'Juan Pérez',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_ERROR');
});

// ---------------------------------------------------------------
// CASE 8: Rate limiting → 429
// ---------------------------------------------------------------
test('rate limiting returns 429 after exceeding attempts', function () {
    $invitation = createValidInvitation();

    // Send 10 requests (the limit) — all should get past throttle
    for ($i = 0; $i < 10; $i++) {
        postJson('/api/v1/auth/register', [
            'invitation_token' => $invitation->token,
            'password' => 'Secret1pass',
            'name' => 'Juan Pérez',
        ]);
    }

    // The 11th request should be rate limited
    $response = postJson('/api/v1/auth/register', [
        'invitation_token' => $invitation->token,
        'password' => 'Secret1pass',
        'name' => 'Juan Pérez',
    ]);

    $response->assertStatus(429);
});
