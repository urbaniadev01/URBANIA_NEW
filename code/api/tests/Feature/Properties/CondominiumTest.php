<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PropertyStatusSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RbacDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
// Helpers (B03-prefixed to avoid collisions)
// ---------------------------------------------------------------

function generateB03AccessToken(User $user): string
{
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function createB03TestOrg(string $name = 'Urbania B03 Org'): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => $name]);
    $org->save();

    return $org;
}

function createB03TestUser(EloquentOrganization $org, string $email = 'b03@urbania.test', string $estado = 'active'): User
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

function createB03AuthUser(string $estado = 'active'): array
{
    $org = createB03TestOrg();
    $user = createB03TestUser($org, 'admin-b03@urbania.test', $estado);
    // Assign admin role with organization scope
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateB03AccessToken($user)];
}

function createB03OtherOrgUser(): array
{
    $org = createB03TestOrg('B03 Other Org');
    $user = createB03TestUser($org, 'other-b03@urbania.test');
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateB03AccessToken($user)];
}

function createB03ResidentUser(): array
{
    $org = createB03TestOrg('B03 Resident Org');
    $user = createB03TestUser($org, 'resident-b03@urbania.test');
    $residentRole = EloquentRole::where('name', 'resident')->first();
    // Assign resident role with unit scope (NO condominium/org scope → CA 18)
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $residentRole->id,
        'scope_type' => 'unit',
        'scope_id' => (string) Str::orderedUuid(),
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateB03AccessToken($user)];
}

function createB03AuthHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

// ---------------------------------------------------------------
// CA 1: GET /condominiums — 200 + lista de condominios de su org
// ---------------------------------------------------------------
test('list condominiums returns condominiums of the user organization', function () {
    $auth = createB03AuthUser();

    $condo = new EloquentCondominium([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Conjunto Las Palmas',
        'direccion' => 'Calle 123',
        'nit' => '900123456-7',
        'created_by' => $auth['user']->id,
    ]);
    $condo->save();

    $response = getJson('/api/v1/condominiums', createB03AuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBe(1);
    expect($data[0]['nombre'])->toBe('Conjunto Las Palmas');
    expect($data[0]['direccion'])->toBe('Calle 123');
    expect($data[0]['nit'])->toBe('900123456-7');
});

// ---------------------------------------------------------------
// CA 2: POST /condominiums — 201 + created_by
// ---------------------------------------------------------------
test('create condominium returns 201 with created_by', function () {
    $auth = createB03AuthUser();

    $response = postJson('/api/v1/condominiums', [
        'nombre' => 'Conjunto El Paraíso',
        'direccion' => 'Avenida Siempre Viva 742',
        'nit' => '800987654-3',
    ], createB03AuthHeader($auth['token']));

    $response->assertCreated();
    $data = $response->json('condominium');
    expect($data['nombre'])->toBe('Conjunto El Paraíso');
    expect($data['direccion'])->toBe('Avenida Siempre Viva 742');
    expect($data['nit'])->toBe('800987654-3');
    expect($data['organization_id'])->toBe($auth['org']->id);
    expect($data['created_by'])->toBe($auth['user']->id);
    expect($data['updated_by'])->toBeNull();
});

// ---------------------------------------------------------------
// CA 3: POST duplicado → 409 CONDOMINIUM_NAME_DUPLICATE
// ---------------------------------------------------------------
test('duplicate condominium name returns 409', function () {
    $auth = createB03AuthUser();

    // Create first
    postJson('/api/v1/condominiums', [
        'nombre' => 'Conjunto Duplicado',
    ], createB03AuthHeader($auth['token']));

    // Duplicate with different case
    $response = postJson('/api/v1/condominiums', [
        'nombre' => 'CONJUNTO DUPLICADO',
    ], createB03AuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('CONDOMINIUM_NAME_DUPLICATE');
});

// ---------------------------------------------------------------
// CA 4: GET /condominiums/{id} — 200 + towers
// ---------------------------------------------------------------
test('show condominium returns detail with towers', function () {
    $auth = createB03AuthUser();

    $condo = new EloquentCondominium([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Conjunto Las Flores',
        'direccion' => 'Calle 456',
        'created_by' => $auth['user']->id,
    ]);
    $condo->save();

    // Create towers for this condominium
    $tower1 = new EloquentTower([
        'condominium_id' => $condo->id,
        'nombre' => 'Torre A',
        'created_by' => $auth['user']->id,
    ]);
    $tower1->save();
    $tower2 = new EloquentTower([
        'condominium_id' => $condo->id,
        'nombre' => 'Torre B',
        'created_by' => $auth['user']->id,
    ]);
    $tower2->save();

    $response = getJson("/api/v1/condominiums/{$condo->id}", createB03AuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('condominium');
    expect($data['nombre'])->toBe('Conjunto Las Flores');
    expect($data['towers'])->toBeArray();
    expect(count($data['towers']))->toBe(2);

    $towerNames = array_column($data['towers'], 'nombre');
    expect($towerNames)->toContain('Torre A');
    expect($towerNames)->toContain('Torre B');
});

// ---------------------------------------------------------------
// CA 5: PATCH /condominiums/{id} — 200 + updated_by
// ---------------------------------------------------------------
test('update condominium returns 200 with updated_by', function () {
    $auth = createB03AuthUser();

    $condo = new EloquentCondominium([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Conjunto Original',
        'direccion' => 'Calle Vieja',
        'nit' => '111222333-4',
        'created_by' => $auth['user']->id,
    ]);
    $condo->save();

    $response = patchJson("/api/v1/condominiums/{$condo->id}", [
        'nombre' => 'Conjunto Renovado',
        'direccion' => 'Calle Nueva 789',
    ], createB03AuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('condominium');
    expect($data['nombre'])->toBe('Conjunto Renovado');
    expect($data['direccion'])->toBe('Calle Nueva 789');
    expect($data['nit'])->toBe('111222333-4'); // unchanged
    expect($data['updated_by'])->toBe($auth['user']->id);
});

// ---------------------------------------------------------------
// CA 6: DELETE condominio con torres → 409 CONDOMINIUM_HAS_TOWERS
// ---------------------------------------------------------------
test('delete condominium with towers returns 409', function () {
    $auth = createB03AuthUser();

    $condo = new EloquentCondominium([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Conjunto Con Torres',
        'created_by' => $auth['user']->id,
    ]);
    $condo->save();

    // Create a tower under this condominium
    $tower = new EloquentTower([
        'condominium_id' => $condo->id,
        'nombre' => 'Torre Única',
        'created_by' => $auth['user']->id,
    ]);
    $tower->save();

    $response = deleteJson("/api/v1/condominiums/{$condo->id}", [], createB03AuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('CONDOMINIUM_HAS_TOWERS');
});

// ---------------------------------------------------------------
// CA 7: DELETE condominio con propiedades → 409 CONDOMINIUM_HAS_PROPERTIES
// ---------------------------------------------------------------
test('delete condominium with properties returns 409', function () {
    $auth = createB03AuthUser();

    $condo = new EloquentCondominium([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Conjunto Con Props',
        'created_by' => $auth['user']->id,
    ]);
    $condo->save();

    // Get system catalogs for property creation
    $type = EloquentPropertyType::query()->whereNull('organization_id')->first();
    $status = EloquentPropertyStatus::query()->whereNull('organization_id')->first();

    // Create a property directly under this condominium (no tower)
    $property = new EloquentProperty([
        'condominium_id' => $condo->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => 'C-101',
        'created_by' => $auth['user']->id,
    ]);
    $property->save();

    $response = deleteJson("/api/v1/condominiums/{$condo->id}", [], createB03AuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('CONDOMINIUM_HAS_PROPERTIES');
});

// ---------------------------------------------------------------
// CA 8: DELETE condominio sin hijos → 204 soft-delete
// ---------------------------------------------------------------
test('delete condominium without children returns 204 soft delete', function () {
    $auth = createB03AuthUser();

    $condo = new EloquentCondominium([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Conjunto Vacío',
        'created_by' => $auth['user']->id,
    ]);
    $condo->save();

    $response = deleteJson("/api/v1/condominiums/{$condo->id}", [], createB03AuthHeader($auth['token']));

    $response->assertNoContent();

    // Verify soft-deleted
    $found = EloquentCondominium::query()->find($condo->id);
    expect($found)->toBeNull();

    $foundWithTrashed = EloquentCondominium::withTrashed()->find($condo->id);
    expect($foundWithTrashed)->not->toBeNull();
    expect($foundWithTrashed->deleted_at)->not->toBeNull();
});

// ---------------------------------------------------------------
// CA 16: Sin auth → 401
// ---------------------------------------------------------------
test('unauthenticated access returns 401', function () {
    $response = getJson('/api/v1/condominiums');

    $response->assertUnauthorized();
});

// ---------------------------------------------------------------
// CA 17: Usuario otra org → 404 (anti-enumeration R-10)
// ---------------------------------------------------------------
test('condominium from another org returns 404', function () {
    $auth = createB03AuthUser();
    $other = createB03OtherOrgUser();

    // Create a condominium under the other org
    $otherCondo = new EloquentCondominium([
        'organization_id' => $other['org']->id,
        'nombre' => 'Conjunto Ajeno',
        'created_by' => $other['user']->id,
    ]);
    $otherCondo->save();

    // Auth user tries to access it
    $response = getJson("/api/v1/condominiums/{$otherCondo->id}", createB03AuthHeader($auth['token']));

    $response->assertNotFound();
    expect($response->json('error.code'))->toBe('CONDOMINIUM_NOT_FOUND');
});

// ---------------------------------------------------------------
// CA 18: Residente → GET /condominiums → 403
// ---------------------------------------------------------------
test('resident cannot list condominiums returns 403', function () {
    $auth = createB03AuthUser();
    $resident = createB03ResidentUser();

    // Create a condominium for auth's org (so data exists)
    $condo = new EloquentCondominium([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Conjunto Visible Para Admin',
        'created_by' => $auth['user']->id,
    ]);
    $condo->save();

    // Resident (with only unit scope) tries to list
    $response = getJson('/api/v1/condominiums', createB03AuthHeader($resident['token']));

    $response->assertForbidden();
});

// ---------------------------------------------------------------
// CA 19: Staff scope_type=condominium, fuera de scope → 404
// ---------------------------------------------------------------
test('staff with condominium scope cannot access other condominium returns 404', function () {
    $org = createB03TestOrg('B03 Staff Org');

    // Create two condominiums in the same org
    $condoA = new EloquentCondominium([
        'organization_id' => $org->id,
        'nombre' => 'Conjunto A',
    ]);
    $condoA->save();

    $condoB = new EloquentCondominium([
        'organization_id' => $org->id,
        'nombre' => 'Conjunto B',
    ]);
    $condoB->save();

    // Create a staff user with condominium scope only for condo A
    $staffUser = createB03TestUser($org, 'staff-b03@urbania.test');
    $managerRole = EloquentRole::where('name', 'manager')->first();
    EloquentRoleAssignment::create([
        'user_id' => $staffUser->id,
        'role_id' => $managerRole->id,
        'scope_type' => 'condominium',
        'scope_id' => $condoA->id,
    ]);
    $staffToken = generateB03AccessToken($staffUser);

    // Staff can access condo A (their scope)
    $responseOk = getJson("/api/v1/condominiums/{$condoA->id}", createB03AuthHeader($staffToken));
    $responseOk->assertOk();

    // Staff tries to access condo B (outside their scope) → 404
    $responseFail = getJson("/api/v1/condominiums/{$condoB->id}", createB03AuthHeader($staffToken));
    $responseFail->assertNotFound();
});
