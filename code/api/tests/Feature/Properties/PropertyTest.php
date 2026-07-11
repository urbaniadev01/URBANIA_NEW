<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\OccupantTypeSeeder;
use Database\Seeders\PropertyStatusSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RbacDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Urbania\Auth\Infrastructure\Models\EloquentContact;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Authorization\Infrastructure\Models\EloquentRole;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;
use Urbania\Directorio\Infrastructure\Models\EloquentOccupantType;
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
    seed(OccupantTypeSeeder::class);
});

// ---------------------------------------------------------------
// Helpers (B04 prefix to avoid collisions)
// ---------------------------------------------------------------

function generateB04AccessToken(User $user): string
{
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function createB04TestOrg(string $name = 'Urbania B04 Org'): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => $name]);
    $org->save();

    return $org;
}

function createB04TestUser(EloquentOrganization $org, string $email = 'b04@urbania.test', string $estado = 'active'): User
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

function createB04AuthUser(string $estado = 'active'): array
{
    $org = createB04TestOrg();
    $user = createB04TestUser($org, 'admin-b04@urbania.test', $estado);
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateB04AccessToken($user)];
}

function createB04AuthHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

function createB04TestCondominium(EloquentOrganization $org, User $user, string $nombre = 'Conjunto B04'): EloquentCondominium
{
    $condo = new EloquentCondominium([
        'organization_id' => $org->id,
        'nombre' => $nombre,
        'created_by' => $user->id,
    ]);
    $condo->save();

    return $condo;
}

function createB04TestTower(EloquentCondominium $condo, User $user, string $nombre = 'Torre B04'): EloquentTower
{
    $tower = new EloquentTower([
        'condominium_id' => $condo->id,
        'nombre' => $nombre,
        'created_by' => $user->id,
    ]);
    $tower->save();

    return $tower;
}

function createB04TestProperty(EloquentCondominium $condo, User $user, ?string $towerId = null, string $codigo = 'A-101'): EloquentProperty
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

function createB04OtherOrgUser(): array
{
    $org = createB04TestOrg('B04 Other Org');
    $user = createB04TestUser($org, 'other-b04@urbania.test');
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateB04AccessToken($user)];
}

function createB04ResidentUser(): array
{
    $org = createB04TestOrg('B04 Resident Org');
    $user = createB04TestUser($org, 'resident-b04@urbania.test');
    $residentRole = EloquentRole::where('name', 'resident')->first();

    return ['org' => $org, 'user' => $user, 'residentRole' => $residentRole];
}

function createB04TowerStaffUser(EloquentOrganization $org, string $towerId): array
{
    $user = createB04TestUser($org, 'tower-staff-b04@urbania.test');
    $managerRole = EloquentRole::where('name', 'manager')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $managerRole->id,
        'scope_type' => 'tower',
        'scope_id' => $towerId,
    ]);

    return ['user' => $user, 'token' => generateB04AccessToken($user)];
}

function createB04TestOccupant(EloquentProperty $property): void
{
    $contact = new EloquentContact([
        'organization_id' => $property->condominium->organization_id,
        'nombre' => 'Ocupante B04',
        'email' => 'ocupante-b04@urbania.test',
    ]);
    $contact->save();

    $occupantType = EloquentOccupantType::query()->whereNull('organization_id')->first();

    DB::table('property_occupants')->insert([
        'id' => (string) Str::orderedUuid(),
        'contact_id' => $contact->id,
        'property_id' => $property->id,
        'occupant_type_id' => $occupantType->id,
        'es_principal' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// ---------------------------------------------------------------
// CA 1: GET /condominiums/{id}/properties — 200 + paginación cursor
// ---------------------------------------------------------------
test('list properties returns cursor-paginated results without area_m2', function () {
    $auth = createB04AuthUser();
    $condo = createB04TestCondominium($auth['org'], $auth['user']);

    // Create 5 properties
    for ($i = 0; $i < 5; $i++) {
        createB04TestProperty($condo, $auth['user'], null, 'U-'.($i + 1));
    }

    $response = getJson(
        "/api/v1/condominiums/{$condo->id}/properties",
        createB04AuthHeader($auth['token']),
    );

    $response->assertOk();
    $json = $response->json();
    expect($json)->toHaveKey('data');
    expect($json)->toHaveKey('meta');
    expect($json['meta'])->toHaveKey('next_cursor');
    expect($json['data'])->toBeArray();
    expect(count($json['data']))->toBe(5);

    // R-10: area_m2 must NOT be present in list
    foreach ($json['data'] as $item) {
        expect($item)->not->toHaveKey('area_m2');
        expect($item)->toHaveKey('codigo');
        expect($item)->toHaveKey('piso');
    }
});

// ---------------------------------------------------------------
// CA 2: GET ...?tower_id=X — filtro por torre
// ---------------------------------------------------------------
test('list properties filtered by tower_id returns only matching units', function () {
    $auth = createB04AuthUser();
    $condo = createB04TestCondominium($auth['org'], $auth['user']);

    $towerA = createB04TestTower($condo, $auth['user'], 'Torre A');
    $towerB = createB04TestTower($condo, $auth['user'], 'Torre B');

    createB04TestProperty($condo, $auth['user'], $towerA->id, 'A-101');
    createB04TestProperty($condo, $auth['user'], $towerA->id, 'A-102');
    createB04TestProperty($condo, $auth['user'], $towerB->id, 'B-101');

    $response = getJson(
        "/api/v1/condominiums/{$condo->id}/properties?tower_id={$towerA->id}",
        createB04AuthHeader($auth['token']),
    );

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(2);
    expect(array_column($data, 'codigo'))->toContain('A-101');
    expect(array_column($data, 'codigo'))->toContain('A-102');
    expect(array_column($data, 'codigo'))->not->toContain('B-101');
});

// ---------------------------------------------------------------
// CA 3: GET ...?search=A-201 — filtro por código
// ---------------------------------------------------------------
test('list properties filtered by search returns matching codigo', function () {
    $auth = createB04AuthUser();
    $condo = createB04TestCondominium($auth['org'], $auth['user']);

    createB04TestProperty($condo, $auth['user'], null, 'A-201-Alpha');
    createB04TestProperty($condo, $auth['user'], null, 'A-202-Beta');
    createB04TestProperty($condo, $auth['user'], null, 'B-101-Gamma');

    $response = getJson(
        "/api/v1/condominiums/{$condo->id}/properties?search=A-201",
        createB04AuthHeader($auth['token']),
    );

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(1);
    expect($data[0]['codigo'])->toBe('A-201-Alpha');
});

// ---------------------------------------------------------------
// CA 4: Filtros combinados tower_id + type_id + status_id
// ---------------------------------------------------------------
test('list properties with combined filters returns intersection', function () {
    $auth = createB04AuthUser();
    $condo = createB04TestCondominium($auth['org'], $auth['user']);

    $towerX = createB04TestTower($condo, $auth['user'], 'Torre X');
    $towerY = createB04TestTower($condo, $auth['user'], 'Torre Y');

    $type1 = EloquentPropertyType::query()->whereNull('organization_id')->first();
    $status1 = EloquentPropertyStatus::query()->whereNull('organization_id')->first();

    // Create two properties in tower X with type1 and status1
    $prop1 = new EloquentProperty([
        'condominium_id' => $condo->id,
        'tower_id' => $towerX->id,
        'property_type_id' => $type1->id,
        'property_status_id' => $status1->id,
        'codigo' => 'X-001',
        'created_by' => $auth['user']->id,
    ]);
    $prop1->save();

    $prop2 = new EloquentProperty([
        'condominium_id' => $condo->id,
        'tower_id' => $towerX->id,
        'property_type_id' => $type1->id,
        'property_status_id' => $status1->id,
        'codigo' => 'X-002',
        'created_by' => $auth['user']->id,
    ]);
    $prop2->save();

    // Create one in tower Y (same type/status) — should be excluded by tower filter
    $prop3 = new EloquentProperty([
        'condominium_id' => $condo->id,
        'tower_id' => $towerY->id,
        'property_type_id' => $type1->id,
        'property_status_id' => $status1->id,
        'codigo' => 'Y-001',
        'created_by' => $auth['user']->id,
    ]);
    $prop3->save();

    $response = getJson(
        "/api/v1/condominiums/{$condo->id}/properties?tower_id={$towerX->id}&type_id={$type1->id}&status_id={$status1->id}",
        createB04AuthHeader($auth['token']),
    );

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(2);
    $codes = array_column($data, 'codigo');
    expect($codes)->toContain('X-001');
    expect($codes)->toContain('X-002');
    expect($codes)->not->toContain('Y-001');
});

// ---------------------------------------------------------------
// CA 5: POST /condominiums/{id}/properties — 201 + created_by
// ---------------------------------------------------------------
test('create property returns 201 with created_by', function () {
    $auth = createB04AuthUser();
    $condo = createB04TestCondominium($auth['org'], $auth['user']);
    $tower = createB04TestTower($condo, $auth['user'], 'Torre Principal');

    $type = EloquentPropertyType::query()->whereNull('organization_id')->first();
    $status = EloquentPropertyStatus::query()->whereNull('organization_id')->first();

    $response = postJson("/api/v1/condominiums/{$condo->id}/properties", [
        'codigo' => 'AP-301',
        'tower_id' => $tower->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'piso' => 3,
        'area_m2' => 82.00,
    ], createB04AuthHeader($auth['token']));

    $response->assertCreated();
    $data = $response->json('property');
    expect($data['codigo'])->toBe('AP-301');
    expect($data['condominium_id'])->toBe($condo->id);
    expect($data['tower_id'])->toBe($tower->id);
    expect($data['piso'])->toBe(3);
    expect((float) $data['area_m2'])->toBe(82.00);
    expect($data['created_by'])->toBe($auth['user']->id);
    expect($data['updated_by'])->toBeNull();
});

// ---------------------------------------------------------------
// CA 6: POST con código duplicado → 409 PROPERTY_CODE_DUPLICATE
// ---------------------------------------------------------------
test('duplicate property code in same condominium returns 409', function () {
    $auth = createB04AuthUser();
    $condo = createB04TestCondominium($auth['org'], $auth['user']);

    $type = EloquentPropertyType::query()->whereNull('organization_id')->first();
    $status = EloquentPropertyStatus::query()->whereNull('organization_id')->first();

    // Create first
    postJson("/api/v1/condominiums/{$condo->id}/properties", [
        'codigo' => 'DUP-101',
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
    ], createB04AuthHeader($auth['token']));

    // Duplicate code
    $response = postJson("/api/v1/condominiums/{$condo->id}/properties", [
        'codigo' => 'DUP-101',
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
    ], createB04AuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('PROPERTY_CODE_DUPLICATE');
});

// ---------------------------------------------------------------
// CA 7: tower_id de otro condominio → 422 TOWER_CONDOMINIUM_MISMATCH
// ---------------------------------------------------------------
test('create property with tower from different condominium returns 422', function () {
    $auth = createB04AuthUser();
    $condoA = createB04TestCondominium($auth['org'], $auth['user'], 'Conjunto A');
    $condoB = createB04TestCondominium($auth['org'], $auth['user'], 'Conjunto B');
    $towerInB = createB04TestTower($condoB, $auth['user'], 'Torre en B');

    $type = EloquentPropertyType::query()->whereNull('organization_id')->first();
    $status = EloquentPropertyStatus::query()->whereNull('organization_id')->first();

    $response = postJson("/api/v1/condominiums/{$condoA->id}/properties", [
        'codigo' => 'MIS-101',
        'tower_id' => $towerInB->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
    ], createB04AuthHeader($auth['token']));

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('TOWER_CONDOMINIUM_MISMATCH');
});

// ---------------------------------------------------------------
// CA 8: GET /properties/{id} — 200 + detalle CON area_m2
// ---------------------------------------------------------------
test('show property returns detail with area_m2', function () {
    $auth = createB04AuthUser();
    $condo = createB04TestCondominium($auth['org'], $auth['user']);
    $tower = createB04TestTower($condo, $auth['user']);
    $property = createB04TestProperty($condo, $auth['user'], $tower->id, 'SHOW-101');

    $response = getJson("/api/v1/properties/{$property->id}", createB04AuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('property');
    expect($data['codigo'])->toBe('SHOW-101');
    expect($data['area_m2'])->toBe(75.50);
    expect($data['condominium_id'])->toBe($condo->id);
    expect($data['created_by'])->toBe($auth['user']->id);

    // R-10: area_m2 IS present in detail
    expect($data)->toHaveKey('area_m2');

    // Nested relations loaded
    expect($data)->toHaveKey('type');
    expect($data)->toHaveKey('status');
    expect($data)->toHaveKey('tower');
});

// ---------------------------------------------------------------
// CA 9: PATCH /properties/{id} — 200 + updated_by
// ---------------------------------------------------------------
test('update property returns 200 with updated_by', function () {
    $auth = createB04AuthUser();
    $condo = createB04TestCondominium($auth['org'], $auth['user']);
    $property = createB04TestProperty($condo, $auth['user'], null, 'OLD-101');

    $response = patchJson("/api/v1/properties/{$property->id}", [
        'codigo' => 'NEW-202',
        'piso' => 5,
        'area_m2' => 100.00,
    ], createB04AuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('property');
    expect($data['codigo'])->toBe('NEW-202');
    expect($data['piso'])->toBe(5);
    expect((float) $data['area_m2'])->toBe(100.00);
    expect($data['updated_by'])->toBe($auth['user']->id);
    // condominium_id unchanged (R-07)
    expect($data['condominium_id'])->toBe($condo->id);
});

// ---------------------------------------------------------------
// CA 10: PATCH con condominium_id → ignorado (inmutable R-07)
// ---------------------------------------------------------------
test('update property ignores condominium_id field immutable', function () {
    $auth = createB04AuthUser();
    // Create two condos
    $condo1 = createB04TestCondominium($auth['org'], $auth['user'], 'Condo 1');
    $condo2 = createB04TestCondominium($auth['org'], $auth['user'], 'Condo 2');
    $property = createB04TestProperty($condo1, $auth['user'], null, 'IMM-101');

    $originalCondoId = $property->condominium_id;

    $response = patchJson("/api/v1/properties/{$property->id}", [
        'codigo' => 'IMM-102',
        'condominium_id' => $condo2->id,
    ], createB04AuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('property');
    expect($data['codigo'])->toBe('IMM-102');
    expect($data['condominium_id'])->toBe($originalCondoId);
    expect($data['condominium_id'])->not->toBe($condo2->id);
});

// ---------------------------------------------------------------
// CA 11: DELETE unidad con ocupantes → 409 PROPERTY_HAS_OCCUPANTS
// ---------------------------------------------------------------
test('delete property with occupants returns 409', function () {
    $auth = createB04AuthUser();
    $condo = createB04TestCondominium($auth['org'], $auth['user']);
    $property = createB04TestProperty($condo, $auth['user'], null, 'OCC-101');

    createB04TestOccupant($property);

    $response = deleteJson("/api/v1/properties/{$property->id}", [], createB04AuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('PROPERTY_HAS_OCCUPANTS');
});

// ---------------------------------------------------------------
// CA 12: DELETE unidad sin ocupantes → 204 soft-delete
// ---------------------------------------------------------------
test('delete property without occupants returns 204 soft delete', function () {
    $auth = createB04AuthUser();
    $condo = createB04TestCondominium($auth['org'], $auth['user']);
    $property = createB04TestProperty($condo, $auth['user'], null, 'DEL-101');

    $response = deleteJson("/api/v1/properties/{$property->id}", [], createB04AuthHeader($auth['token']));

    $response->assertNoContent();

    // Verify soft-deleted
    $found = EloquentProperty::query()->find($property->id);
    expect($found)->toBeNull();

    $foundWithTrashed = EloquentProperty::withTrashed()->find($property->id);
    expect($foundWithTrashed)->not->toBeNull();
    expect($foundWithTrashed->deleted_at)->not->toBeNull();
});

// ---------------------------------------------------------------
// CA 13: Sin auth → 401
// ---------------------------------------------------------------
test('unauthenticated access returns 401', function () {
    $response = getJson('/api/v1/condominiums/some-uuid/properties');
    $response->assertUnauthorized();

    $response2 = getJson('/api/v1/properties/some-uuid');
    $response2->assertUnauthorized();
});

// ---------------------------------------------------------------
// CA 14: Usuario otra org → 404 (anti-enumeración R-10)
// ---------------------------------------------------------------
test('property from another org returns 404', function () {
    $auth = createB04AuthUser();
    $other = createB04OtherOrgUser();

    $otherCondo = createB04TestCondominium($other['org'], $other['user'], 'Condo Ajeno');
    $otherProperty = createB04TestProperty($otherCondo, $other['user'], null, 'AJEN-101');

    $response = getJson("/api/v1/properties/{$otherProperty->id}", createB04AuthHeader($auth['token']));

    $response->assertNotFound();
    expect($response->json('error.code'))->toBe('PROPERTY_NOT_FOUND');
});

// ---------------------------------------------------------------
// CA 15: Residente → GET /condominiums/{id}/properties → 403
// ---------------------------------------------------------------
test('resident cannot list properties returns 403', function () {
    $auth = createB04AuthUser();
    $res = createB04ResidentUser();

    $condo = createB04TestCondominium($res['org'], $auth['user'], 'Resident Condo');

    // Assign resident role with unit scope
    EloquentRoleAssignment::create([
        'user_id' => $res['user']->id,
        'role_id' => $res['residentRole']->id,
        'scope_type' => 'unit',
        'scope_id' => (string) Str::orderedUuid(),
    ]);
    $residentToken = generateB04AccessToken($res['user']);

    // Resident tries to list properties → 403
    $response = getJson(
        "/api/v1/condominiums/{$condo->id}/properties",
        createB04AuthHeader($residentToken),
    );

    $response->assertForbidden();
    expect($response->json('error.code'))->toBe('FORBIDDEN');
});

// ---------------------------------------------------------------
// CA 16: Residente → GET /properties/{id} (su unidad) → 200
// ---------------------------------------------------------------
test('resident can see their own property returns 200', function () {
    $auth = createB04AuthUser();
    $res = createB04ResidentUser();

    // Use same org so the property is accessible via tenant isolation
    $condo = new EloquentCondominium([
        'organization_id' => $res['org']->id,
        'nombre' => 'Resident Condo Own',
        'created_by' => $auth['user']->id,
    ]);
    $condo->save();

    $tower = createB04TestTower($condo, $auth['user'], 'Resident Tower');
    $property = createB04TestProperty($condo, $auth['user'], $tower->id, 'RES-UNIT-1');

    // Assign resident with unit scope for this specific property
    EloquentRoleAssignment::create([
        'user_id' => $res['user']->id,
        'role_id' => $res['residentRole']->id,
        'scope_type' => 'unit',
        'scope_id' => $property->id,
    ]);
    $residentToken = generateB04AccessToken($res['user']);

    // Resident accesses their own unit
    $response = getJson("/api/v1/properties/{$property->id}", createB04AuthHeader($residentToken));

    $response->assertOk();
    $data = $response->json('property');
    expect($data['codigo'])->toBe('RES-UNIT-1');
    expect($data)->toHaveKey('area_m2');
    expect($data['area_m2'])->toBe(75.50);
});

// ---------------------------------------------------------------
// CA 17: Residente → GET /properties/{id} (unidad ajena) → 404
// ---------------------------------------------------------------
test('resident cannot see other property returns 404', function () {
    $auth = createB04AuthUser();
    $res = createB04ResidentUser();

    // Same org for tenant isolation
    $condo = new EloquentCondominium([
        'organization_id' => $res['org']->id,
        'nombre' => 'Resident Condo Others',
        'created_by' => $auth['user']->id,
    ]);
    $condo->save();

    $ownProperty = createB04TestProperty($condo, $auth['user'], null, 'OWN-UNIT');
    $otherProperty = createB04TestProperty($condo, $auth['user'], null, 'OTHER-UNIT');

    // Assign resident with unit scope for ownProperty only
    EloquentRoleAssignment::create([
        'user_id' => $res['user']->id,
        'role_id' => $res['residentRole']->id,
        'scope_type' => 'unit',
        'scope_id' => $ownProperty->id,
    ]);
    $residentToken = generateB04AccessToken($res['user']);

    // Resident tries to access otherProperty → 404 (anti-enumeration)
    $response = getJson("/api/v1/properties/{$otherProperty->id}", createB04AuthHeader($residentToken));

    $response->assertNotFound();
    expect($response->json('error.code'))->toBe('PROPERTY_NOT_FOUND');
});

// ---------------------------------------------------------------
// CA 18: Staff scope_type=condominium en condo A → GET /condominiums/{B_id}/properties → 404
// ---------------------------------------------------------------
test('staff with condominium scope cannot access other condominium properties returns 404', function () {
    $org = createB04TestOrg('B04 Staff Condo Org');

    $condoA = new EloquentCondominium([
        'organization_id' => $org->id,
        'nombre' => 'Condo A (scope)',
    ]);
    $condoA->save();

    $condoB = new EloquentCondominium([
        'organization_id' => $org->id,
        'nombre' => 'Condo B (fuera scope)',
    ]);
    $condoB->save();

    // Create properties in condo B (data exists)
    $adminUser = createB04TestUser($org, 'admin-condo-scope@urbania.test');
    createB04TestProperty($condoB, $adminUser, null, 'B-PROP-1');

    // Staff user with condominium scope only for condo A
    $staffUser = createB04TestUser($org, 'staff-condo-b04@urbania.test');
    $managerRole = EloquentRole::where('name', 'manager')->first();
    EloquentRoleAssignment::create([
        'user_id' => $staffUser->id,
        'role_id' => $managerRole->id,
        'scope_type' => 'condominium',
        'scope_id' => $condoA->id,
    ]);
    $staffToken = generateB04AccessToken($staffUser);

    // Staff tries condo A properties → should succeed
    createB04TestProperty($condoA, $adminUser, null, 'A-PROP-1');
    $responseOk = getJson("/api/v1/condominiums/{$condoA->id}/properties", createB04AuthHeader($staffToken));
    $responseOk->assertOk();

    // Staff tries condo B properties → 404 (outside scope)
    $responseFail = getJson("/api/v1/condominiums/{$condoB->id}/properties", createB04AuthHeader($staffToken));
    $responseFail->assertNotFound();
});

// ---------------------------------------------------------------
// CA 19: Staff scope_type=tower en torre X → GET /properties/{id} (otra torre) → 404
// ---------------------------------------------------------------
test('staff with tower scope cannot access property in other tower returns 404', function () {
    $org = createB04TestOrg('B04 Staff Tower Org');

    $condo = new EloquentCondominium([
        'organization_id' => $org->id,
        'nombre' => 'Staff Tower Condo',
    ]);
    $condo->save();

    $adminUser = createB04TestUser($org, 'admin-tower-scope@urbania.test');

    $towerX = createB04TestTower($condo, $adminUser, 'Torre X (scope)');
    $towerY = createB04TestTower($condo, $adminUser, 'Torre Y (fuera scope)');

    $propertyX = createB04TestProperty($condo, $adminUser, $towerX->id, 'X-UNIT');
    $propertyY = createB04TestProperty($condo, $adminUser, $towerY->id, 'Y-UNIT');

    // Staff with tower scope for tower X only
    $staff = createB04TowerStaffUser($org, $towerX->id);

    // Staff can access property in tower X
    $responseOk = getJson("/api/v1/properties/{$propertyX->id}", createB04AuthHeader($staff['token']));
    $responseOk->assertOk();

    // Staff tries property in tower Y → 404 (outside scope)
    $responseFail = getJson("/api/v1/properties/{$propertyY->id}", createB04AuthHeader($staff['token']));
    $responseFail->assertNotFound();
    expect($responseFail->json('error.code'))->toBe('PROPERTY_NOT_FOUND');
});
