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

use function Pest\Laravel\getJson;
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
// Helpers (B05T prefix to avoid collisions with coefficient test)
// ---------------------------------------------------------------

function generateB05TAccessToken(User $user): string
{
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function createB05TTestOrg(string $name = 'Urbania B05T Org'): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => $name]);
    $org->save();

    return $org;
}

function createB05TTestUser(EloquentOrganization $org, string $email = 'b05t@urbania.test', string $estado = 'active'): User
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

function createB05TAuthUser(string $estado = 'active'): array
{
    $org = createB05TTestOrg();
    $user = createB05TTestUser($org, 'admin-b05t@urbania.test', $estado);
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateB05TAccessToken($user)];
}

function createB05TAuthHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

function createB05TTestCondominium(EloquentOrganization $org, User $user, string $nombre = 'Conjunto B05T'): EloquentCondominium
{
    $condo = new EloquentCondominium([
        'organization_id' => $org->id,
        'nombre' => $nombre,
        'created_by' => $user->id,
    ]);
    $condo->save();

    return $condo;
}

function createB05TTestTower(EloquentCondominium $condo, User $user, string $nombre = 'Torre B05T'): EloquentTower
{
    $tower = new EloquentTower([
        'condominium_id' => $condo->id,
        'nombre' => $nombre,
        'created_by' => $user->id,
    ]);
    $tower->save();

    return $tower;
}

function createB05TTestProperty(EloquentCondominium $condo, User $user, ?string $towerId = null, string $codigo = 'A-101'): EloquentProperty
{
    $type = EloquentPropertyType::query()->whereNull('organization_id')->first();
    $status = EloquentPropertyStatus::query()->whereNull('organization_id')->first();

    $property = new EloquentProperty([
        'condominium_id' => $condo->id,
        'tower_id' => $towerId,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => $codigo,
        'piso' => 1,
        'area_m2' => 75.50,
        'created_by' => $user->id,
    ]);
    $property->save();

    return $property;
}

function createB05TResidentUser(): array
{
    $org = createB05TTestOrg('B05T Resident Org');
    $user = createB05TTestUser($org, 'resident-b05t@urbania.test');
    $residentRole = EloquentRole::where('name', 'resident')->first();

    return ['org' => $org, 'user' => $user, 'residentRole' => $residentRole];
}

function createB05TCondominiumStaffUser(EloquentOrganization $org, string $condominiumId): array
{
    $user = createB05TTestUser($org, 'staff-condo-b05t@urbania.test');
    $managerRole = EloquentRole::where('name', 'manager')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $managerRole->id,
        'scope_type' => 'condominium',
        'scope_id' => $condominiumId,
    ]);

    return ['user' => $user, 'token' => generateB05TAccessToken($user)];
}

function createB05TTowerStaffUser(EloquentOrganization $org, string $towerId): array
{
    $user = createB05TTestUser($org, 'staff-tower-b05t@urbania.test');
    $managerRole = EloquentRole::where('name', 'manager')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $managerRole->id,
        'scope_type' => 'tower',
        'scope_id' => $towerId,
    ]);

    return ['user' => $user, 'token' => generateB05TAccessToken($user)];
}

function createB05TOtherOrgUser(): array
{
    $org = createB05TTestOrg('B05T Other Org');
    $user = createB05TTestUser($org, 'other-b05t@urbania.test');
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateB05TAccessToken($user)];
}

// ---------------------------------------------------------------
// CA 9: Admin → GET /condominiums/{id}/tree → 200 + hierarchical structure
// ---------------------------------------------------------------
test('tree returns hierarchical structure with towers and property counts', function () {
    $auth = createB05TAuthUser();
    $condo = createB05TTestCondominium($auth['org'], $auth['user']);
    $towerA = createB05TTestTower($condo, $auth['user'], 'Torre A');
    $towerB = createB05TTestTower($condo, $auth['user'], 'Torre B');

    // Create properties under towers
    createB05TTestProperty($condo, $auth['user'], $towerA->id, 'A-101');
    createB05TTestProperty($condo, $auth['user'], $towerA->id, 'A-102');
    createB05TTestProperty($condo, $auth['user'], $towerB->id, 'B-101');

    // Create untowered property
    createB05TTestProperty($condo, $auth['user'], null, 'C-001');

    $response = getJson(
        "/api/v1/condominiums/{$condo->id}/tree",
        createB05TAuthHeader($auth['token']),
    );

    $response->assertOk();
    $tree = $response->json('tree');

    expect($tree)->toHaveKey('id');
    expect($tree['id'])->toBe($condo->id);
    expect($tree['nombre'])->toBe('Conjunto B05T');
    expect($tree)->toHaveKey('towers');
    expect($tree)->toHaveKey('untowered_properties_count');

    $towersData = $tree['towers'];
    expect(count($towersData))->toBe(2);

    // Check tower A
    $towerAData = collect($towersData)->firstWhere('nombre', 'Torre A');
    expect($towerAData)->not->toBeNull();
    expect($towerAData['properties_count'])->toBe(2);

    // Check tower B
    $towerBData = collect($towersData)->firstWhere('nombre', 'Torre B');
    expect($towerBData)->not->toBeNull();
    expect($towerBData['properties_count'])->toBe(1);

    // Check untowered count
    expect($tree['untowered_properties_count'])->toBe(1);
});

// ---------------------------------------------------------------
// CA 10 (tree): Unauthenticated → 401
// ---------------------------------------------------------------
test('unauthenticated user cannot access tree', function () {
    $response = getJson('/api/v1/condominiums/some-uuid/tree');
    $response->assertUnauthorized();
});

// ---------------------------------------------------------------
// CA 14 (tree): User from other org → 404
// ---------------------------------------------------------------
test('user from other org gets 404 for tree', function () {
    $auth = createB05TAuthUser();
    $condo = createB05TTestCondominium($auth['org'], $auth['user']);

    $other = createB05TOtherOrgUser();

    $response = getJson(
        "/api/v1/condominiums/{$condo->id}/tree",
        createB05TAuthHeader($other['token']),
    );

    // R-10: 404 for resources outside org (anti-enumeration)
    $response->assertStatus(404);
    expect($response->json('error.code'))->toBe('CONDOMINIUM_NOT_FOUND');
});

// ---------------------------------------------------------------
// CA 15: Resident → tree → 403 (via scope → 404)
// ---------------------------------------------------------------
test('resident cannot view tree', function () {
    $auth = createB05TAuthUser();
    $condo = createB05TTestCondominium($auth['org'], $auth['user']);

    $residentData = createB05TResidentUser();

    $response = getJson(
        "/api/v1/condominiums/{$condo->id}/tree",
        createB05TAuthHeader(generateB05TAccessToken($residentData['user'])),
    );

    // Resident has no condo/org scope → 404 (R-10 anti-enumeration)
    $response->assertStatus(404);
    expect($response->json('error.code'))->toBe('CONDOMINIUM_NOT_FOUND');
});

// ---------------------------------------------------------------
// CA 17: Tower staff → tree → 403 (tower scope insufficient → 404)
// ---------------------------------------------------------------
test('tower staff cannot view tree', function () {
    $auth = createB05TAuthUser();
    $condo = createB05TTestCondominium($auth['org'], $auth['user']);
    $tower = createB05TTestTower($condo, $auth['user'], 'Torre X');

    $towerStaffData = createB05TTowerStaffUser($auth['org'], $tower->id);

    $response = getJson(
        "/api/v1/condominiums/{$condo->id}/tree",
        createB05TAuthHeader($towerStaffData['token']),
    );

    // Tower scope is insufficient for tree (R-09-bis exception) → 404
    $response->assertStatus(404);
    expect($response->json('error.code'))->toBe('CONDOMINIUM_NOT_FOUND');
});

// ---------------------------------------------------------------
// Condominium staff within their scope can view tree
// ---------------------------------------------------------------
test('condominium staff can view tree for their assigned condominium', function () {
    $auth = createB05TAuthUser();
    $condo = createB05TTestCondominium($auth['org'], $auth['user']);

    $staffData = createB05TCondominiumStaffUser($auth['org'], $condo->id);

    $response = getJson(
        "/api/v1/condominiums/{$condo->id}/tree",
        createB05TAuthHeader($staffData['token']),
    );

    $response->assertOk();
    $tree = $response->json('tree');
    expect($tree['id'])->toBe($condo->id);
});

// ---------------------------------------------------------------
// Condominium staff cannot view tree for unassigned condominium
// ---------------------------------------------------------------
test('condominium staff cannot view tree for unassigned condominium', function () {
    $auth = createB05TAuthUser();
    $condoA = createB05TTestCondominium($auth['org'], $auth['user'], 'Conjunto A');
    $condoB = createB05TTestCondominium($auth['org'], $auth['user'], 'Conjunto B');

    $staffData = createB05TCondominiumStaffUser($auth['org'], $condoA->id);

    $response = getJson(
        "/api/v1/condominiums/{$condoB->id}/tree",
        createB05TAuthHeader($staffData['token']),
    );

    // Outside scope → 404 (R-10 anti-enumeration)
    $response->assertStatus(404);
});
