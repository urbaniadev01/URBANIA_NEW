<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PropertyStatusSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RbacDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Authorization\Infrastructure\Models\EloquentRole;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyStatus;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyType;
use Urbania\Properties\Infrastructure\Models\EloquentTower;
use Urbania\Shared\JWT\JwtService;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------
// Setup: ensure JWT keys exist and seed catalogs
// ---------------------------------------------------------------

beforeEach(function (): void {
    app()->forgetInstance(JwtService::class);

    $dir = storage_path('jwt');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $privatePath = $dir.DIRECTORY_SEPARATOR.'private.pem';
    $publicPath = $dir.DIRECTORY_SEPARATOR.'public.pem';

    if (! file_exists($privatePath) || ! file_exists($publicPath)) {
        $pair = JwtService::generateTestKeyPair();
        file_put_contents($privatePath, $pair['private']);
        file_put_contents($publicPath, $pair['public']);
    }

    config([
        'jwt.private_key' => $privatePath,
        'jwt.public_key' => $publicPath,
    ]);

    // Seed RBAC roles + system property catalogs
    seed(RbacDemoSeeder::class);
    seed(PropertyTypeSeeder::class);
    seed(PropertyStatusSeeder::class);
});

// ---------------------------------------------------------------
// Helpers (B03T prefix to avoid collisions)
// ---------------------------------------------------------------

function generateB03TAccessToken(User $user): string
{
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function createB03TTestOrg(string $name = 'Urbania B03T Org'): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => $name]);
    $org->save();

    return $org;
}

function createB03TTestUser(EloquentOrganization $org, string $email = 'b03t@urbania.test', string $estado = 'active'): User
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

function createB03TAuthUser(string $estado = 'active'): array
{
    $org = createB03TTestOrg();
    $user = createB03TTestUser($org, 'admin-b03t@urbania.test', $estado);
    // Assign admin role with organization scope
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateB03TAccessToken($user)];
}

function createB03TAuthHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

/**
 * Create a test condominium with the given user as creator.
 */
function createB03TTestCondominium(EloquentOrganization $org, User $user, string $nombre = 'Conjunto Test'): EloquentCondominium
{
    $condo = new EloquentCondominium([
        'organization_id' => $org->id,
        'nombre' => $nombre,
        'created_by' => $user->id,
    ]);
    $condo->save();

    return $condo;
}

// ---------------------------------------------------------------
// CA 9: GET /condominiums/{id}/towers — 200 + lista
// ---------------------------------------------------------------
test('list towers for a condominium returns towers', function () {
    $auth = createB03TAuthUser();
    $condo = createB03TTestCondominium($auth['org'], $auth['user']);

    // Create towers under this condominium
    $tower1 = new EloquentTower([
        'condominium_id' => $condo->id,
        'nombre' => 'Torre Norte',
        'created_by' => $auth['user']->id,
    ]);
    $tower1->save();
    $tower2 = new EloquentTower([
        'condominium_id' => $condo->id,
        'nombre' => 'Torre Sur',
        'created_by' => $auth['user']->id,
    ]);
    $tower2->save();

    $response = getJson("/api/v1/condominiums/{$condo->id}/towers", createB03TAuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBe(2);

    $names = array_column($data, 'nombre');
    expect($names)->toContain('Torre Norte');
    expect($names)->toContain('Torre Sur');
});

// ---------------------------------------------------------------
// CA 10: POST /condominiums/{id}/towers — 201 + created_by
// ---------------------------------------------------------------
test('create tower returns 201 with created_by', function () {
    $auth = createB03TAuthUser();
    $condo = createB03TTestCondominium($auth['org'], $auth['user']);

    $response = postJson("/api/v1/condominiums/{$condo->id}/towers", [
        'nombre' => 'Torre Central',
    ], createB03TAuthHeader($auth['token']));

    $response->assertCreated();
    $data = $response->json('tower');
    expect($data['nombre'])->toBe('Torre Central');
    expect($data['condominium_id'])->toBe($condo->id);
    expect($data['created_by'])->toBe($auth['user']->id);
    expect($data['updated_by'])->toBeNull();
});

// ---------------------------------------------------------------
// CA 11: POST duplicado → 409 TOWER_NAME_DUPLICATE
// ---------------------------------------------------------------
test('duplicate tower name in same condominium returns 409', function () {
    $auth = createB03TAuthUser();
    $condo = createB03TTestCondominium($auth['org'], $auth['user']);

    // Create first
    postJson("/api/v1/condominiums/{$condo->id}/towers", [
        'nombre' => 'Torre Duplicada',
    ], createB03TAuthHeader($auth['token']));

    // Duplicate with different case
    $response = postJson("/api/v1/condominiums/{$condo->id}/towers", [
        'nombre' => 'TORRE DUPLICADA',
    ], createB03TAuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('TOWER_NAME_DUPLICATE');
});

// ---------------------------------------------------------------
// CA 12: PATCH /towers/{id} — 200 + updated_by
// ---------------------------------------------------------------
test('update tower returns 200 with updated_by', function () {
    $auth = createB03TAuthUser();
    $condo = createB03TTestCondominium($auth['org'], $auth['user']);

    $tower = new EloquentTower([
        'condominium_id' => $condo->id,
        'nombre' => 'Torre Vieja',
        'created_by' => $auth['user']->id,
    ]);
    $tower->save();

    $response = patchJson("/api/v1/towers/{$tower->id}", [
        'nombre' => 'Torre Renovada',
    ], createB03TAuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('tower');
    expect($data['nombre'])->toBe('Torre Renovada');
    expect($data['updated_by'])->toBe($auth['user']->id);
});

// ---------------------------------------------------------------
// CA 13: PATCH /towers/{id} con condominium_id → ignorado (R-07)
// ---------------------------------------------------------------
test('update tower ignores condominium_id field immutable', function () {
    $auth = createB03TAuthUser();

    // Create two condominiums
    $condo1 = createB03TTestCondominium($auth['org'], $auth['user'], 'Conjunto 1');
    $condo2 = createB03TTestCondominium($auth['org'], $auth['user'], 'Conjunto 2');

    // Create a tower under condo1
    $tower = new EloquentTower([
        'condominium_id' => $condo1->id,
        'nombre' => 'Torre Inmutable',
        'created_by' => $auth['user']->id,
    ]);
    $tower->save();
    $originalCondoId = $tower->condominium_id;

    // Try to change condominium_id via PATCH (should be ignored per R-07)
    $response = patchJson("/api/v1/towers/{$tower->id}", [
        'nombre' => 'Torre Con Nuevo Nombre',
        'condominium_id' => $condo2->id, // This should be ignored
    ], createB03TAuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('tower');
    expect($data['nombre'])->toBe('Torre Con Nuevo Nombre');
    expect($data['condominium_id'])->toBe($originalCondoId); // unchanged
    expect($data['condominium_id'])->not->toBe($condo2->id);
});

// ---------------------------------------------------------------
// CA 14: DELETE torre con propiedades → 409 TOWER_HAS_PROPERTIES
// ---------------------------------------------------------------
test('delete tower with properties returns 409', function () {
    $auth = createB03TAuthUser();
    $condo = createB03TTestCondominium($auth['org'], $auth['user']);

    $tower = new EloquentTower([
        'condominium_id' => $condo->id,
        'nombre' => 'Torre Ocupada',
        'created_by' => $auth['user']->id,
    ]);
    $tower->save();

    // Get system catalogs
    $type = EloquentPropertyType::query()->whereNull('organization_id')->first();
    $status = EloquentPropertyStatus::query()->whereNull('organization_id')->first();

    // Create a property in this tower
    $property = new EloquentProperty([
        'condominium_id' => $condo->id,
        'tower_id' => $tower->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => 'T-101',
        'created_by' => $auth['user']->id,
    ]);
    $property->save();

    $response = deleteJson("/api/v1/towers/{$tower->id}", [], createB03TAuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('TOWER_HAS_PROPERTIES');
});

// ---------------------------------------------------------------
// CA 15: DELETE torre sin propiedades → 204 soft-delete
// ---------------------------------------------------------------
test('delete tower without properties returns 204 soft delete', function () {
    $auth = createB03TAuthUser();
    $condo = createB03TTestCondominium($auth['org'], $auth['user']);

    $tower = new EloquentTower([
        'condominium_id' => $condo->id,
        'nombre' => 'Torre Vacía',
        'created_by' => $auth['user']->id,
    ]);
    $tower->save();

    $response = deleteJson("/api/v1/towers/{$tower->id}", [], createB03TAuthHeader($auth['token']));

    $response->assertNoContent();

    // Verify soft-deleted
    $found = EloquentTower::query()->find($tower->id);
    expect($found)->toBeNull();

    $foundWithTrashed = EloquentTower::withTrashed()->find($tower->id);
    expect($foundWithTrashed)->not->toBeNull();
    expect($foundWithTrashed->deleted_at)->not->toBeNull();
});

// ---------------------------------------------------------------
// CA 20: Staff scope_type=tower, fuera de scope → 404
// ---------------------------------------------------------------
test('staff with tower scope cannot access other tower returns 404', function () {
    $org = createB03TTestOrg('B03T Staff Org');

    // Create admin to set up data
    $adminUser = createB03TTestUser($org, 'admin-tower-staff@urbania.test');
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $adminUser->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);
    $adminToken = generateB03TAccessToken($adminUser);

    $condo = createB03TTestCondominium($org, $adminUser, 'Conjunto Staff Towers');

    // Create two towers in the same condominium
    $towerX = new EloquentTower([
        'condominium_id' => $condo->id,
        'nombre' => 'Torre X (scope)',
        'created_by' => $adminUser->id,
    ]);
    $towerX->save();

    $towerY = new EloquentTower([
        'condominium_id' => $condo->id,
        'nombre' => 'Torre Y (fuera de scope)',
        'created_by' => $adminUser->id,
    ]);
    $towerY->save();

    // Create a staff user with tower scope only for tower X
    $staffUser = createB03TTestUser($org, 'tower-staff-b03@urbania.test');
    $managerRole = EloquentRole::where('name', 'manager')->first();
    EloquentRoleAssignment::create([
        'user_id' => $staffUser->id,
        'role_id' => $managerRole->id,
        'scope_type' => 'tower',
        'scope_id' => $towerX->id,
    ]);
    $staffToken = generateB03TAccessToken($staffUser);

    // Staff can access tower X (their scope)
    $responseOk = getJson("/api/v1/towers/{$towerX->id}", createB03TAuthHeader($staffToken));
    $responseOk->assertOk();

    // Staff tries to PATCH tower Y (outside their scope) → 404 (R-09-bis)
    $responseFail = patchJson("/api/v1/towers/{$towerY->id}", [
        'nombre' => 'Tower Y Hacked',
    ], createB03TAuthHeader($staffToken));

    $responseFail->assertNotFound();
});
