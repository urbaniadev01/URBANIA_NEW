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
use Urbania\Properties\Infrastructure\Models\EloquentPropertyCoefficient;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyStatus;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyType;
use Urbania\Shared\JWT\JwtService;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
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
// Helpers (B05 prefix to avoid collisions)
// ---------------------------------------------------------------

function generateB05AccessToken(User $user): string
{
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function createB05TestOrg(string $name = 'Urbania B05 Org'): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => $name]);
    $org->save();

    return $org;
}

function createB05TestUser(EloquentOrganization $org, string $email = 'b05@urbania.test', string $estado = 'active'): User
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

function createB05AuthUser(string $estado = 'active'): array
{
    $org = createB05TestOrg();
    $user = createB05TestUser($org, 'admin-b05@urbania.test', $estado);
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateB05AccessToken($user)];
}

function createB05AuthHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

function createB05TestCondominium(EloquentOrganization $org, User $user, string $nombre = 'Conjunto B05'): EloquentCondominium
{
    $condo = new EloquentCondominium([
        'organization_id' => $org->id,
        'nombre' => $nombre,
        'created_by' => $user->id,
    ]);
    $condo->save();

    return $condo;
}

function createB05TestProperty(EloquentCondominium $condo, User $user, string $codigo = 'A-101', ?string $towerId = null, float $areaM2 = 75.50): EloquentProperty
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
        'area_m2' => $areaM2,
        'created_by' => $user->id,
    ]);
    $property->save();

    return $property;
}

function createB05TestCoefficient(EloquentProperty $property, User $user, string $tipo = 'copropiedad', float $valor = 0.25, ?string $vigenteHasta = null): EloquentPropertyCoefficient
{
    $coeff = new EloquentPropertyCoefficient([
        'property_id' => $property->id,
        'tipo' => $tipo,
        'valor' => $valor,
        'vigente_desde' => now()->subMonth()->toDateString(),
        'vigente_hasta' => $vigenteHasta,
        'created_by' => $user->id,
    ]);
    $coeff->save();

    return $coeff;
}

function createB05ResidentUser(): array
{
    $org = createB05TestOrg('B05 Resident Org');
    $user = createB05TestUser($org, 'resident-b05@urbania.test');
    $residentRole = EloquentRole::where('name', 'resident')->first();

    return ['org' => $org, 'user' => $user, 'residentRole' => $residentRole];
}

function createB05CondominiumStaffUser(EloquentOrganization $org, string $condominiumId): array
{
    $user = createB05TestUser($org, 'staff-condo-b05@urbania.test');
    $managerRole = EloquentRole::where('name', 'manager')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $managerRole->id,
        'scope_type' => 'condominium',
        'scope_id' => $condominiumId,
    ]);

    return ['user' => $user, 'token' => generateB05AccessToken($user)];
}

function createB05TowerStaffUser(EloquentOrganization $org, string $towerId): array
{
    $user = createB05TestUser($org, 'staff-tower-b05@urbania.test');
    $managerRole = EloquentRole::where('name', 'manager')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $managerRole->id,
        'scope_type' => 'tower',
        'scope_id' => $towerId,
    ]);

    return ['user' => $user, 'token' => generateB05AccessToken($user)];
}

function createB05OtherOrgUser(): array
{
    $org = createB05TestOrg('B05 Other Org');
    $user = createB05TestUser($org, 'other-b05@urbania.test');
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateB05AccessToken($user)];
}

// ---------------------------------------------------------------
// CA 1: GET /properties/{id}/coefficients — 200 + lista
// ---------------------------------------------------------------
test('list coefficients returns all coefficients for a property', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $property = createB05TestProperty($condo, $auth['user']);

    // Create active and historical coefficients
    createB05TestCoefficient($property, $auth['user'], 'copropiedad', 0.25);
    createB05TestCoefficient($property, $auth['user'], 'parqueadero', 0.10);
    createB05TestCoefficient($property, $auth['user'], 'copropiedad', 0.30, now()->subDays(10)->toDateString());

    $response = getJson(
        "/api/v1/properties/{$property->id}/coefficients",
        createB05AuthHeader($auth['token']),
    );

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBe(3);
    // Should include both active and historical
    $tipos = array_column($data, 'tipo');
    expect($tipos)->toContain('copropiedad');
    expect($tipos)->toContain('parqueadero');
});

// ---------------------------------------------------------------
// CA 2: PATCH /condominiums/{id}/coefficients — 200 + created_by
// ---------------------------------------------------------------
test('patch coefficients creates coefficients with created_by', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $p1 = createB05TestProperty($condo, $auth['user'], 'U-1');
    $p2 = createB05TestProperty($condo, $auth['user'], 'U-2');

    $response = patchJson(
        "/api/v1/condominiums/{$condo->id}/coefficients",
        [
            'items' => [
                ['property_id' => $p1->id, 'tipo' => 'copropiedad', 'valor' => 0.6],
                ['property_id' => $p2->id, 'tipo' => 'copropiedad', 'valor' => 0.4],
            ],
        ],
        createB05AuthHeader($auth['token']),
    );

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBe(2);
    expect($data[0]['created_by'])->toBe($auth['user']->id);
    expect($data[1]['created_by'])->toBe($auth['user']->id);
});

// ---------------------------------------------------------------
// CA 3: R-05 — closing previous active coefficient
// ---------------------------------------------------------------
test('creating new coefficient closes previous active one', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $property = createB05TestProperty($condo, $auth['user']);

    // Create initial active coefficient
    createB05TestCoefficient($property, $auth['user'], 'copropiedad', 0.5);

    // Create new coefficient for same property+tipo via PATCH
    $response = patchJson(
        "/api/v1/condominiums/{$condo->id}/coefficients",
        [
            'items' => [
                ['property_id' => $property->id, 'tipo' => 'copropiedad', 'valor' => 0.75],
            ],
        ],
        createB05AuthHeader($auth['token']),
    );

    $response->assertOk();

    // Check DB: old coefficient should have vigente_hasta set
    $oldCoeff = EloquentPropertyCoefficient::query()
        ->where('property_id', $property->id)
        ->where('tipo', 'copropiedad')
        ->whereNotNull('vigente_hasta')
        ->first();
    expect($oldCoeff)->not->toBeNull();
    expect($oldCoeff->updated_by)->toBe($auth['user']->id);

    // Check DB: new coefficient should be active (vigente_hasta NULL)
    $newCoeff = EloquentPropertyCoefficient::query()
        ->where('property_id', $property->id)
        ->where('tipo', 'copropiedad')
        ->whereNull('vigente_hasta')
        ->first();
    expect($newCoeff)->not->toBeNull();
    expect((float) $newCoeff->valor)->toBe(0.75);
});

// ---------------------------------------------------------------
// CA 4: valor out of range → 422 COEFFICIENT_OUT_OF_RANGE
// ---------------------------------------------------------------
test('coefficient value out of range returns 422 COEFFICIENT_OUT_OF_RANGE', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $property = createB05TestProperty($condo, $auth['user']);

    $response = patchJson(
        "/api/v1/condominiums/{$condo->id}/coefficients",
        [
            'items' => [
                ['property_id' => $property->id, 'tipo' => 'copropiedad', 'valor' => 1.5],
            ],
        ],
        createB05AuthHeader($auth['token']),
    );

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('COEFFICIENT_OUT_OF_RANGE');
});

test('negative coefficient value returns 422 COEFFICIENT_OUT_OF_RANGE', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $property = createB05TestProperty($condo, $auth['user']);

    $response = patchJson(
        "/api/v1/condominiums/{$condo->id}/coefficients",
        [
            'items' => [
                ['property_id' => $property->id, 'tipo' => 'copropiedad', 'valor' => -0.1],
            ],
        ],
        createB05AuthHeader($auth['token']),
    );

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('COEFFICIENT_OUT_OF_RANGE');
});

// ---------------------------------------------------------------
// CA 5: tipo not recognized → 422 COEFFICIENT_INVALID_TYPE
// ---------------------------------------------------------------
test('invalid coefficient type returns 422 COEFFICIENT_INVALID_TYPE', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $property = createB05TestProperty($condo, $auth['user']);

    $response = patchJson(
        "/api/v1/condominiums/{$condo->id}/coefficients",
        [
            'items' => [
                ['property_id' => $property->id, 'tipo' => 'jardin', 'valor' => 0.1],
            ],
        ],
        createB05AuthHeader($auth['token']),
    );

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('COEFFICIENT_INVALID_TYPE');
});

// ---------------------------------------------------------------
// CA 6: Sum of copropiedad ≠ 1.0 → 200 + warnings
// ---------------------------------------------------------------
test('copropiedad sum not 1 returns 200 with COEFFICIENT_SUM_MISMATCH warning', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $p1 = createB05TestProperty($condo, $auth['user'], 'U-1');
    $p2 = createB05TestProperty($condo, $auth['user'], 'U-2');

    $response = patchJson(
        "/api/v1/condominiums/{$condo->id}/coefficients",
        [
            'items' => [
                ['property_id' => $p1->id, 'tipo' => 'copropiedad', 'valor' => 0.3],
                ['property_id' => $p2->id, 'tipo' => 'copropiedad', 'valor' => 0.3],
            ],
        ],
        createB05AuthHeader($auth['token']),
    );

    $response->assertOk();
    $warnings = $response->json('warnings');
    expect($warnings)->toBeArray();
    expect(count($warnings))->toBe(1);
    expect($warnings[0]['code'])->toBe('COEFFICIENT_SUM_MISMATCH');
    expect($warnings[0]['detail']['condominium_id'])->toBe($condo->id);
    expect($warnings[0]['detail']['sum'])->toBe(0.6);
});

test('copropiedad sum exactly 1 returns 200 without warnings', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $p1 = createB05TestProperty($condo, $auth['user'], 'U-1');
    $p2 = createB05TestProperty($condo, $auth['user'], 'U-2');

    $response = patchJson(
        "/api/v1/condominiums/{$condo->id}/coefficients",
        [
            'items' => [
                ['property_id' => $p1->id, 'tipo' => 'copropiedad', 'valor' => 0.6],
                ['property_id' => $p2->id, 'tipo' => 'copropiedad', 'valor' => 0.4],
            ],
        ],
        createB05AuthHeader($auth['token']),
    );

    $response->assertOk();
    expect($response->json('warnings'))->toBeNull();
});

// ---------------------------------------------------------------
// CA 7: property_id not in condominium → 422 PROPERTY_NOT_IN_CONDOMINIUM
// ---------------------------------------------------------------
test('property not in condominium returns 422 PROPERTY_NOT_IN_CONDOMINIUM', function () {
    $auth = createB05AuthUser();
    $condo1 = createB05TestCondominium($auth['org'], $auth['user'], 'Conjunto 1');
    $condo2 = createB05TestCondominium($auth['org'], $auth['user'], 'Conjunto 2');
    $property = createB05TestProperty($condo2, $auth['user']);

    // Try to add coefficient through condominium 1 for a property in condominium 2
    $response = patchJson(
        "/api/v1/condominiums/{$condo1->id}/coefficients",
        [
            'items' => [
                ['property_id' => $property->id, 'tipo' => 'copropiedad', 'valor' => 0.5],
            ],
        ],
        createB05AuthHeader($auth['token']),
    );

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('PROPERTY_NOT_IN_CONDOMINIUM');
});

// ---------------------------------------------------------------
// CA 8: Atomicity — multi-item PATCH with one invalid → full rollback
// ---------------------------------------------------------------
test('patch with invalid item rolls back entire transaction', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $p1 = createB05TestProperty($condo, $auth['user'], 'U-1');
    $p2 = createB05TestProperty($condo, $auth['user'], 'U-2');

    // Create an existing coefficient for p1 to verify rollback
    createB05TestCoefficient($p1, $auth['user'], 'parqueadero', 0.10);

    // Send: first item valid, second item invalid (wrong condominium)
    $otherOrg = createB05OtherOrgUser();
    $otherCondo = createB05TestCondominium($otherOrg['org'], $otherOrg['user'], 'Other Condo');
    $p3 = createB05TestProperty($otherCondo, $otherOrg['user']);

    $response = patchJson(
        "/api/v1/condominiums/{$condo->id}/coefficients",
        [
            'items' => [
                ['property_id' => $p1->id, 'tipo' => 'parqueadero', 'valor' => 0.20],
                ['property_id' => $p3->id, 'tipo' => 'copropiedad', 'valor' => 0.50],
            ],
        ],
        createB05AuthHeader($auth['token']),
    );

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('PROPERTY_NOT_IN_CONDOMINIUM');

    // Verify p1's coefficient was NOT modified (rollback)
    $p1Coeff = EloquentPropertyCoefficient::query()
        ->where('property_id', $p1->id)
        ->where('tipo', 'parqueadero')
        ->whereNull('vigente_hasta')
        ->first();
    expect($p1Coeff)->not->toBeNull();
    expect((float) $p1Coeff->valor)->toBe(0.10); // Original value preserved
});

// ---------------------------------------------------------------
// CA 10: Unauthenticated → 401
// ---------------------------------------------------------------
test('unauthenticated user cannot access coefficient endpoints', function () {
    $response = getJson('/api/v1/properties/some-uuid/coefficients');
    $response->assertUnauthorized();
});

test('unauthenticated user cannot patch coefficients', function () {
    $response = patchJson('/api/v1/condominiums/some-uuid/coefficients', [
        'items' => [],
    ]);
    $response->assertUnauthorized();
});

// ---------------------------------------------------------------
// CA 11: Resident cannot PATCH coefficients → 403 (via scope → 404)
// ---------------------------------------------------------------
test('resident cannot manage coefficients', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $property = createB05TestProperty($condo, $auth['user']);

    $residentData = createB05ResidentUser();

    // Resident tries to patch coefficients
    $response = patchJson(
        "/api/v1/condominiums/{$condo->id}/coefficients",
        [
            'items' => [
                ['property_id' => $property->id, 'tipo' => 'copropiedad', 'valor' => 0.5],
            ],
        ],
        createB05AuthHeader(generateB05AccessToken($residentData['user'])),
    );

    // Resident has no condo/org scope → 404 (R-10 anti-enumeration)
    $response->assertStatus(404);
    expect($response->json('error.code'))->toBe('CONDOMINIUM_NOT_FOUND');
});

// ---------------------------------------------------------------
// CA 12: Resident can see own property coefficients → 200
// ---------------------------------------------------------------
test('resident can see own property coefficients', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $property = createB05TestProperty($condo, $auth['user']);

    createB05TestCoefficient($property, $auth['user'], 'copropiedad', 0.25);

    // Create resident with unit scope for this property
    $residentData = createB05ResidentUser();
    EloquentRoleAssignment::create([
        'user_id' => $residentData['user']->id,
        'role_id' => $residentData['residentRole']->id,
        'scope_type' => 'unit',
        'scope_id' => $property->id,
    ]);

    $response = getJson(
        "/api/v1/properties/{$property->id}/coefficients",
        createB05AuthHeader(generateB05AccessToken($residentData['user'])),
    );

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBe(1);
});

// ---------------------------------------------------------------
// CA 13: Resident sees 404 for other property's coefficients
// ---------------------------------------------------------------
test('resident cannot see other property coefficients', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $p1 = createB05TestProperty($condo, $auth['user'], 'U-1');
    $p2 = createB05TestProperty($condo, $auth['user'], 'U-2');

    // Create resident with unit scope for p1 only
    $residentData = createB05ResidentUser();
    EloquentRoleAssignment::create([
        'user_id' => $residentData['user']->id,
        'role_id' => $residentData['residentRole']->id,
        'scope_type' => 'unit',
        'scope_id' => $p1->id,
    ]);

    // Try to see p2's coefficients
    $response = getJson(
        "/api/v1/properties/{$p2->id}/coefficients",
        createB05AuthHeader(generateB05AccessToken($residentData['user'])),
    );

    // R-10: 404 for properties outside scope (anti-enumeration)
    $response->assertStatus(404);
    expect($response->json('error.code'))->toBe('PROPERTY_NOT_FOUND');
});

// ---------------------------------------------------------------
// CA 14: User from other org → 404 (anti-enumeration)
// ---------------------------------------------------------------
test('user from other org gets 404 for coefficients', function () {
    $auth = createB05AuthUser();
    $condo = createB05TestCondominium($auth['org'], $auth['user']);
    $property = createB05TestProperty($condo, $auth['user']);

    $other = createB05OtherOrgUser();

    $response = getJson(
        "/api/v1/properties/{$property->id}/coefficients",
        createB05AuthHeader($other['token']),
    );

    // R-10: 404 unificado con 403 (anti-enumeration)
    $response->assertStatus(404);
    expect($response->json('error.code'))->toBe('PROPERTY_NOT_FOUND');
});

// ---------------------------------------------------------------
// CA 16: Staff with condominium scope in condo A → PATCH condo B → 404
// ---------------------------------------------------------------
test('staff with condominium scope cannot manage coefficients for other condominium', function () {
    $auth = createB05AuthUser();
    $condoA = createB05TestCondominium($auth['org'], $auth['user'], 'Conjunto A');
    $condoB = createB05TestCondominium($auth['org'], $auth['user'], 'Conjunto B');
    $property = createB05TestProperty($condoB, $auth['user']);

    $staffData = createB05CondominiumStaffUser($auth['org'], $condoA->id);

    $response = patchJson(
        "/api/v1/condominiums/{$condoB->id}/coefficients",
        [
            'items' => [
                ['property_id' => $property->id, 'tipo' => 'copropiedad', 'valor' => 0.5],
            ],
        ],
        createB05AuthHeader($staffData['token']),
    );

    // R-09-bis: outside scope → 404 (R-10 anti-enumeration)
    $response->assertStatus(404);
});

// ---------------------------------------------------------------
// CA 17: Tower staff → tree → 403 (via scope → 404)
// This is tested in the Tree tests below
// ---------------------------------------------------------------
