<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PropertyStatusSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyStatus;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyType;
use Urbania\Shared\JWT\JwtService;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------
// Setup: ensure JWT keys exist and seed system catalogs
// ---------------------------------------------------------------

beforeEach(function (): void {
    app()->forgetInstance(JwtService::class);

    $dir = storage_path('jwt');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Generate JWT keys once per test so they are available before any request
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

    // Seed system catalogs (organization_id IS NULL)
    seed(PropertyTypeSeeder::class);
    seed(PropertyStatusSeeder::class);
});

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

function generatePropTypeAccessToken(User $user): string
{
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function createPropTypeTestOrg(string $name = 'Urbania Test Org'): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => $name]);
    $org->save();

    return $org;
}

function createPropTypeTestUser(EloquentOrganization $org, string $email = 'test@urbania.test', string $estado = 'active'): User
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

function createPropTypeAuthUser(string $estado = 'active'): array
{
    $org = createPropTypeTestOrg();
    $user = createPropTypeTestUser($org, 'admin@urbania.test', $estado);

    return ['org' => $org, 'user' => $user, 'token' => generatePropTypeAccessToken($user)];
}

function createPropTypeResidentUser(): array
{
    $org = createPropTypeTestOrg('Resident Org');
    $user = createPropTypeTestUser($org, 'residente@urbania.test');

    return ['org' => $org, 'user' => $user, 'token' => generatePropTypeAccessToken($user)];
}

function createPropTypeOtherOrgUser(): array
{
    $org = createPropTypeTestOrg('Other Org');
    $user = createPropTypeTestUser($org, 'other@urbania.test');

    return ['org' => $org, 'user' => $user, 'token' => generatePropTypeAccessToken($user)];
}

function createPropTypeAuthHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

// ---------------------------------------------------------------
// CASE 1: GET /property-types — 200 + lista (sistema + org)
// ---------------------------------------------------------------
test('list property types returns system types plus org types', function () {
    $auth = createPropTypeAuthUser();

    // Create a custom type for this org
    $customType = new EloquentPropertyType([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Penthouse',
        'descripcion' => 'Penthouse de lujo',
        'created_by' => $auth['user']->id,
    ]);
    $customType->save();

    $response = getJson('/api/v1/property-types', createPropTypeAuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();

    // Should include 5 system types + 1 custom = 6
    expect(count($data))->toBeGreaterThanOrEqual(6);

    // System types should be present
    $nombres = array_column($data, 'nombre');
    expect($nombres)->toContain('Apartamento');
    expect($nombres)->toContain('Penthouse');
});

// ---------------------------------------------------------------
// CASE 2: POST /property-types — 201 + created_by
// ---------------------------------------------------------------
test('create property type returns 201 with created_by', function () {
    $auth = createPropTypeAuthUser();

    $response = postJson('/api/v1/property-types', [
        'nombre' => 'Oficina',
        'descripcion' => 'Unidad de oficina',
    ], createPropTypeAuthHeader($auth['token']));

    $response->assertCreated();
    $data = $response->json('property_type');
    expect($data['nombre'])->toBe('Oficina');
    expect($data['organization_id'])->toBe($auth['org']->id);
    expect($data['created_by'])->toBe($auth['user']->id);
});

// ---------------------------------------------------------------
// CASE 3: POST /property-types — 409 duplicado
// ---------------------------------------------------------------
test('duplicate property type name returns 409', function () {
    $auth = createPropTypeAuthUser();

    // Create first
    postJson('/api/v1/property-types', [
        'nombre' => 'Oficina',
        'descripcion' => 'Oficina',
    ], createPropTypeAuthHeader($auth['token']));

    // Duplicate with different case
    $response = postJson('/api/v1/property-types', [
        'nombre' => 'OFICINA',
        'descripcion' => 'Otra oficina',
    ], createPropTypeAuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('PROPERTY_TYPE_NAME_DUPLICATE');
});

// ---------------------------------------------------------------
// CASE 4: PATCH /property-types/{id} — 200 + updated_by
// ---------------------------------------------------------------
test('update property type returns 200 with updated_by', function () {
    $auth = createPropTypeAuthUser();

    $type = new EloquentPropertyType([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Estudio',
        'descripcion' => 'Estudio',
        'created_by' => $auth['user']->id,
    ]);
    $type->save();

    $response = patchJson("/api/v1/property-types/{$type->id}", [
        'nombre' => 'Loft',
        'descripcion' => 'Loft moderno',
    ], createPropTypeAuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('property_type');
    expect($data['nombre'])->toBe('Loft');
    expect($data['descripcion'])->toBe('Loft moderno');
    expect($data['updated_by'])->toBe($auth['user']->id);
});

// ---------------------------------------------------------------
// CASE 5: PATCH tipo sistema → 403 SYSTEM_CATALOG_READONLY
// ---------------------------------------------------------------
test('update system property type returns 403', function () {
    $auth = createPropTypeAuthUser();

    $systemType = EloquentPropertyType::query()
        ->whereNull('organization_id')
        ->first();

    $response = patchJson("/api/v1/property-types/{$systemType->id}", [
        'nombre' => 'Modificado',
    ], createPropTypeAuthHeader($auth['token']));

    $response->assertForbidden();
    expect($response->json('error.code'))->toBe('SYSTEM_CATALOG_READONLY');
});

// ---------------------------------------------------------------
// CASE 6: DELETE tipo propio sin uso — 204
// ---------------------------------------------------------------
test('delete own property type without usage returns 204', function () {
    $auth = createPropTypeAuthUser();

    $type = new EloquentPropertyType([
        'organization_id' => $auth['org']->id,
        'nombre' => 'A eliminar',
        'created_by' => $auth['user']->id,
    ]);
    $type->save();

    $response = deleteJson("/api/v1/property-types/{$type->id}", [], createPropTypeAuthHeader($auth['token']));

    $response->assertNoContent();

    // Verify soft-deleted
    $found = EloquentPropertyType::query()->find($type->id);
    expect($found)->toBeNull();

    $foundWithTrashed = EloquentPropertyType::withTrashed()->find($type->id);
    expect($foundWithTrashed)->not->toBeNull();
    expect($foundWithTrashed->deleted_at)->not->toBeNull();
});

// ---------------------------------------------------------------
// CASE 7: DELETE tipo en uso → 409 PROPERTY_TYPE_IN_USE
// ---------------------------------------------------------------
test('delete property type in use returns 409', function () {
    $auth = createPropTypeAuthUser();

    $type = new EloquentPropertyType([
        'organization_id' => $auth['org']->id,
        'nombre' => 'En uso',
        'created_by' => $auth['user']->id,
    ]);
    $type->save();

    // Get a system status to use as FK
    $status = EloquentPropertyStatus::query()
        ->whereNull('organization_id')
        ->first();

    // Create a condominium and property referencing this type
    $condominium = new EloquentCondominium([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Test Condo',
        'created_by' => $auth['user']->id,
    ]);
    $condominium->save();

    $property = new EloquentProperty([
        'condominium_id' => $condominium->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => 'A-101',
        'created_by' => $auth['user']->id,
    ]);
    $property->save();

    $response = deleteJson("/api/v1/property-types/{$type->id}", [], createPropTypeAuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('PROPERTY_TYPE_IN_USE');
});

// ---------------------------------------------------------------
// CASE 8: DELETE tipo sistema → 403
// ---------------------------------------------------------------
test('delete system property type returns 403', function () {
    $auth = createPropTypeAuthUser();

    $systemType = EloquentPropertyType::query()
        ->whereNull('organization_id')
        ->first();

    $response = deleteJson("/api/v1/property-types/{$systemType->id}", [], createPropTypeAuthHeader($auth['token']));

    $response->assertForbidden();
    expect($response->json('error.code'))->toBe('SYSTEM_CATALOG_READONLY');
});

// ---------------------------------------------------------------
// CASE 9: Sin auth → 401
// ---------------------------------------------------------------
test('unauthenticated access returns 401', function () {
    $response = getJson('/api/v1/property-types');

    $response->assertUnauthorized();
});

// ---------------------------------------------------------------
// CASE 14: Rol residente GET → 200
// ---------------------------------------------------------------
test('resident role can list property types', function () {
    $resident = createPropTypeResidentUser();

    $response = getJson('/api/v1/property-types', createPropTypeAuthHeader($resident['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBeGreaterThanOrEqual(5); // system types
});

// ---------------------------------------------------------------
// CASE 15: Otra org GET → filtrado
// ---------------------------------------------------------------
test('other org types are not visible', function () {
    $auth = createPropTypeAuthUser();

    // Create a type for auth's org
    $ownType = new EloquentPropertyType([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Propio',
        'created_by' => $auth['user']->id,
    ]);
    $ownType->save();

    // Create a type for another org
    $other = createPropTypeOtherOrgUser();
    $otherType = new EloquentPropertyType([
        'organization_id' => $other['org']->id,
        'nombre' => 'Ajeno',
        'created_by' => $other['user']->id,
    ]);
    $otherType->save();

    $response = getJson('/api/v1/property-types', createPropTypeAuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    $nombres = array_column($data, 'nombre');

    expect($nombres)->toContain('Propio');
    expect($nombres)->not->toContain('Ajeno'); // tenant isolation
});
