<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Urbania\Auth\Infrastructure\Models\EloquentContact;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Authorization\Infrastructure\Models\EloquentPermission;
use Urbania\Authorization\Infrastructure\Models\EloquentRole;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;
use Urbania\Shared\JWT\JwtService;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Setup: ensure JWT keys exist and JwtService singleton is fresh
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    app()->forgetInstance(JwtService::class);

    $dir = storage_path('jwt');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function generateMeAccessToken(User $user): string
{
    $pair = JwtService::generateTestKeyPair();

    $dir = storage_path('jwt');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $privatePath = $dir.'/private.pem';
    $publicPath = $dir.'/public.pem';

    file_put_contents($privatePath, $pair['private']);
    file_put_contents($publicPath, $pair['public']);

    config([
        'jwt.private_key' => $privatePath,
        'jwt.public_key' => $publicPath,
    ]);

    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function createUserWithRoleAndPermissions(): array
{
    $org = new EloquentOrganization(['nombre' => 'Urbania Test']);
    $org->save();

    $user = new User([
        'organization_id' => $org->id,
        'email' => 'me-test@urbania.test',
        'password_hash' => Hash::make('Secret1pass'),
        'estado' => 'active',
    ]);
    $user->save();

    // Create contact (required for name resolution)
    $contact = new EloquentContact([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'nombre' => 'John Doe',
        'email' => 'me-test@urbania.test',
    ]);
    $contact->save();

    // Create role
    $role = new EloquentRole(['name' => 'admin']);
    $role->save();

    // Create permissions
    $perm1 = new EloquentPermission(['name' => 'admin.access']);
    $perm1->save();

    $perm2 = new EloquentPermission(['name' => 'condominiums.read']);
    $perm2->save();

    // Attach permissions to role
    $role->permissions()->attach([$perm1->id, $perm2->id]);

    // Assign role to user
    $assignment = new EloquentRoleAssignment([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);
    $assignment->save();

    $token = generateMeAccessToken($user);

    return [
        'user' => $user,
        'token' => $token,
    ];
}

// ---------------------------------------------------------------
// CASE 1: authenticated user with role and permissions → 200
// ---------------------------------------------------------------
test('authenticated user returns user data with role and permissions', function () {
    $data = createUserWithRoleAndPermissions();

    $response = getJson('/api/v1/auth/me', [
        'Authorization' => 'Bearer '.$data['token'],
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'user' => [
                'id',
                'email',
                'name',
                'role',
                'permissions',
            ],
        ])
        ->assertJsonPath('user.email', 'me-test@urbania.test')
        ->assertJsonPath('user.name', 'John Doe')
        ->assertJsonPath('user.role', 'admin')
        ->assertJsonPath('user.permissions', ['admin.access', 'condominiums.read']);

    // Verify id is a UUID string
    $id = $response->json('user.id');
    expect($id)->toBeString();
    expect(strlen($id))->toBe(36); // UUID length
});

// ---------------------------------------------------------------
// CASE 2: no token → 401
// ---------------------------------------------------------------
test('request without token returns 401', function () {
    $response = getJson('/api/v1/auth/me');

    $response->assertUnauthorized();
});

// ---------------------------------------------------------------
// CASE 3: invalid token → 401
// ---------------------------------------------------------------
test('request with invalid token returns 401', function () {
    $response = getJson('/api/v1/auth/me', [
        'Authorization' => 'Bearer invalid.token.here',
    ]);

    $response->assertUnauthorized();
});

// ---------------------------------------------------------------
// CASE 3-bis: expired token → 401
// ---------------------------------------------------------------
test('request with expired token returns 401', function () {
    // Generate a token that explicitly expired 1 hour ago
    $pair = JwtService::generateTestKeyPair();

    $dir = storage_path('jwt');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $privatePath = $dir.'/private.pem';
    $publicPath = $dir.'/public.pem';

    file_put_contents($privatePath, $pair['private']);
    file_put_contents($publicPath, $pair['public']);

    config([
        'jwt.private_key' => $privatePath,
        'jwt.public_key' => $publicPath,
    ]);

    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    $expiredToken = $jwtService->issueAccessToken('00000000-0000-0000-0000-000000000000', ['exp' => time() - 3600]);

    $response = getJson('/api/v1/auth/me', [
        'Authorization' => 'Bearer '.$expiredToken,
    ]);

    $response->assertUnauthorized();
});
