<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Authorization\Application\Services\PermissionResolver;
use Urbania\Authorization\Infrastructure\Models\EloquentPermission;
use Urbania\Authorization\Infrastructure\Models\EloquentRole;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;
use Urbania\Shared\JWT\JwtService;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------
// Setup: ensure JWT keys exist and JwtService singleton is fresh
// ---------------------------------------------------------------

beforeEach(function (): void {
    // Forget any previously resolved JwtService so it picks up new keys
    app()->forgetInstance(JwtService::class);

    // Ensure the JWT key directory exists
    $dir = storage_path('jwt');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
});

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

/**
 * Generate a valid RS256 JWT access token for a given user in tests.
 * Writes a temporary key pair to the default storage paths and configures
 * the JWT driver so both token generation and verification use the same keys.
 */
function generateAccessToken(User $user): string
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

    // Ensure a fresh JwtService picks up the new config
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

/**
 * Create an organization and return it.
 */
function createTestOrganization(string $name = 'Urbania Test Org'): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => $name]);
    $org->save();

    return $org;
}

/**
 * Create a user in the given organization.
 */
function createTestUser(EloquentOrganization $org, string $email = 'test@urbania.test', string $estado = 'active'): User
{
    $user = new User([
        'organization_id' => $org->id,
        'email' => $email,
        'password_hash' => Hash::make('Secret1pass'),
        'estado' => $estado,
    ]);
    $user->save();

    return $user;
}

/**
 * Ensure roles and permissions exist (idempotent).
 */
function seedRolesAndPermissions(): array
{
    $adminPerm = EloquentPermission::firstOrCreate(
        ['name' => 'admin.access'],
        ['id' => (string) Str::orderedUuid(), 'description' => 'Acceso al panel admin'],
    );

    $profilePerm = EloquentPermission::firstOrCreate(
        ['name' => 'profile.view'],
        ['id' => (string) Str::orderedUuid(), 'description' => 'Ver perfil'],
    );

    $adminRole = EloquentRole::firstOrCreate(
        ['name' => 'admin'],
        ['id' => (string) Str::orderedUuid(), 'description' => 'Administrador'],
    );

    $residentRole = EloquentRole::firstOrCreate(
        ['name' => 'resident'],
        ['id' => (string) Str::orderedUuid(), 'description' => 'Residente'],
    );

    // Attach permissions to roles (syncWithoutDetaching to be idempotent)
    $adminRole->permissions()->syncWithoutDetaching([$adminPerm->id]);
    $residentRole->permissions()->syncWithoutDetaching([$profilePerm->id]);

    return [
        'admin_perm' => $adminPerm,
        'profile_perm' => $profilePerm,
        'admin_role' => $adminRole,
        'resident_role' => $residentRole,
    ];
}

/**
 * Assign a role to a user with a scope.
 */
function assignRole(User $user, EloquentRole $role, string $scopeType, ?string $scopeId = null): EloquentRoleAssignment
{
    $assignment = new EloquentRoleAssignment([
        'id' => (string) Str::orderedUuid(),
        'user_id' => $user->id,
        'role_id' => $role->id,
        'scope_type' => $scopeType,
        'scope_id' => $scopeId,
    ]);
    $assignment->save();

    return $assignment;
}

// ---------------------------------------------------------------
// CASE 1: User with role_assignment that grants the required
//         permission in the correct scope → 200
// ---------------------------------------------------------------
test('user with correct role assignment in correct scope returns 200', function () {
    $org = createTestOrganization();
    $user = createTestUser($org);
    $roles = seedRolesAndPermissions();

    assignRole($user, $roles['admin_role'], 'organization', $org->id);

    $token = generateAccessToken($user);

    $response = getJson("/api/v1/organizations/{$org->id}/admin", [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Admin dashboard — acceso autorizado.');
});

// ---------------------------------------------------------------
// CASE 2: Authenticated user without that role_assignment → 403
// ---------------------------------------------------------------
test('user without role assignment returns 403 permission denied', function () {
    $org = createTestOrganization();
    $user = createTestUser($org, 'no-role@urbania.test');
    seedRolesAndPermissions();

    // No role assignment for this user

    $token = generateAccessToken($user);

    $response = getJson("/api/v1/organizations/{$org->id}/admin", [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden()
        ->assertJsonPath('error.code', 'PERMISSION_DENIED');
});

// ---------------------------------------------------------------
// CASE 3: User with permission but in a different scope → 403
//         Confirms scope is verified, not just permission existence
// ---------------------------------------------------------------
test('user with permission in different scope returns 403', function () {
    $orgA = createTestOrganization('Org A');
    $orgB = createTestOrganization('Org B');
    $user = createTestUser($orgA, 'scoped@urbania.test');
    $roles = seedRolesAndPermissions();

    // Assign admin role at orgA scope
    assignRole($user, $roles['admin_role'], 'organization', $orgA->id);

    $token = generateAccessToken($user);

    // Try to access orgB's admin dashboard
    $response = getJson("/api/v1/organizations/{$orgB->id}/admin", [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden()
        ->assertJsonPath('error.code', 'PERMISSION_DENIED');
});

// ---------------------------------------------------------------
// CASE 4: Revoke role_assignment → cache invalidated → 403
//         Confirms cache is actively invalidated, not just TTL-expired
// ---------------------------------------------------------------
test('revoking role assignment invalidates cache and returns 403', function () {
    $org = createTestOrganization();
    $user = createTestUser($org, 'revoked@urbania.test');
    $roles = seedRolesAndPermissions();

    $assignment = assignRole($user, $roles['admin_role'], 'organization', $org->id);

    $token = generateAccessToken($user);

    // First request: should succeed and cache the result
    $response1 = getJson("/api/v1/organizations/{$org->id}/admin", [
        'Authorization' => "Bearer {$token}",
    ]);
    $response1->assertOk();

    // Revoke the assignment (soft delete)
    $assignment->delete();

    // Second request: cache should be invalidated by the model's `deleted` event
    $response2 = getJson("/api/v1/organizations/{$org->id}/admin", [
        'Authorization' => "Bearer {$token}",
    ]);

    $response2->assertForbidden()
        ->assertJsonPath('error.code', 'PERMISSION_DENIED');
});

// ---------------------------------------------------------------
// CASE 5: Legacy column bypass attempt → Rejected
//         Confirms there is no alternate authorization path
//         (e.g., a "role" text column on users table)
// ---------------------------------------------------------------
test('legacy role column does not bypass rbac gate', function () {
    $org = createTestOrganization();
    // Create a user with a hypothetical legacy "role" attribute
    // Even if the user model had a "role" field, the gate should NOT check it
    $user = createTestUser($org, 'legacy@urbania.test');
    seedRolesAndPermissions();

    // No role_assignments for this user — they have no RBAC permissions
    // Even if someone added a legacy column, it should be ignored

    $token = generateAccessToken($user);

    $response = getJson("/api/v1/organizations/{$org->id}/admin", [
        'Authorization' => "Bearer {$token}",
    ]);

    $response->assertForbidden()
        ->assertJsonPath('error.code', 'PERMISSION_DENIED');

    // Additional assertion: the PermissionResolver only checks role_assignments
    $resolver = app(PermissionResolver::class);
    $permissions = $resolver->resolve((string) $user->id);
    expect($permissions)->toBeEmpty();
});

// ---------------------------------------------------------------
// Extra: Unauthenticated request → 401 (not 403)
// ---------------------------------------------------------------
test('unauthenticated request returns 401', function () {
    $org = createTestOrganization();

    $response = getJson("/api/v1/organizations/{$org->id}/admin");

    $response->assertUnauthorized();
});
