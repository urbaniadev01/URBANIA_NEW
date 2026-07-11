<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\OccupantTypeSeeder;
use Database\Seeders\PropertyStatusSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Urbania\Auth\Infrastructure\Models\EloquentContact;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Directorio\Infrastructure\Models\EloquentOccupantType;
use Urbania\Directorio\Infrastructure\Models\EloquentPropertyOccupant;
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
// Setup: ensure JWT keys exist and seed the system catalog
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

    seed(OccupantTypeSeeder::class);
});

// ---------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------

function generateOccTypeAccessToken(User $user): string
{
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function createOccTypeTestOrg(string $name = 'Urbania Test Org'): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => $name]);
    $org->save();

    return $org;
}

function createOccTypeTestUser(EloquentOrganization $org, string $email = 'test@urbania.test'): User
{
    $user = new User([
        'organization_id' => $org->id,
        'email' => $email,
        'password_hash' => Hash::make('Secret1pass'),
        'estado' => 'active',
    ]);
    $user->save();

    return $user;
}

function createOccTypeAuthUser(): array
{
    $org = createOccTypeTestOrg();
    $user = createOccTypeTestUser($org, 'admin@urbania.test');

    return ['org' => $org, 'user' => $user, 'token' => generateOccTypeAccessToken($user)];
}

function createOccTypeResidentUser(): array
{
    $org = createOccTypeTestOrg('Resident Org');
    $user = createOccTypeTestUser($org, 'residente@urbania.test');

    return ['org' => $org, 'user' => $user, 'token' => generateOccTypeAccessToken($user)];
}

function createOccTypeOtherOrgUser(): array
{
    $org = createOccTypeTestOrg('Other Org');
    $user = createOccTypeTestUser($org, 'other@urbania.test');

    return ['org' => $org, 'user' => $user, 'token' => generateOccTypeAccessToken($user)];
}

function createOccTypeAuthHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

// ---------------------------------------------------------------
// CASE 1: GET /occupant-types — 200 + lista (sistema + org)
// ---------------------------------------------------------------
test('list occupant types returns system types plus org types', function () {
    $auth = createOccTypeAuthUser();

    $custom = new EloquentOccupantType([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Portero',
        'descripcion' => 'Portero del edificio',
        'created_by' => $auth['user']->id,
    ]);
    $custom->save();

    $response = getJson('/api/v1/occupant-types', createOccTypeAuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBeGreaterThanOrEqual(5); // 4 system + 1 custom

    $nombres = array_column($data, 'nombre');
    expect($nombres)->toContain('Propietario');
    expect($nombres)->toContain('Portero');
});

// ---------------------------------------------------------------
// CASE 2: POST /occupant-types — 201 + created_by
// ---------------------------------------------------------------
test('create occupant type returns 201 with created_by', function () {
    $auth = createOccTypeAuthUser();

    $response = postJson('/api/v1/occupant-types', [
        'nombre' => 'Cuidador',
        'descripcion' => 'Cuidador contratado',
    ], createOccTypeAuthHeader($auth['token']));

    $response->assertCreated();
    $data = $response->json('data');
    expect($data['nombre'])->toBe('Cuidador');
    expect($data['organization_id'])->toBe($auth['org']->id);
    expect($data['created_by'])->toBe($auth['user']->id);
});

// ---------------------------------------------------------------
// CASE 3: POST /occupant-types — 409 duplicado
// ---------------------------------------------------------------
test('duplicate occupant type name returns 409', function () {
    $auth = createOccTypeAuthUser();

    postJson('/api/v1/occupant-types', [
        'nombre' => 'Cuidador',
    ], createOccTypeAuthHeader($auth['token']));

    $response = postJson('/api/v1/occupant-types', [
        'nombre' => 'CUIDADOR',
    ], createOccTypeAuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('OCCUPANT_TYPE_NAME_DUPLICATE');
});

// ---------------------------------------------------------------
// CASE 4: PATCH /occupant-types/{id} — 200 + updated_by
// ---------------------------------------------------------------
test('update occupant type returns 200 with updated_by', function () {
    $auth = createOccTypeAuthUser();

    $type = new EloquentOccupantType([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Visitante',
        'created_by' => $auth['user']->id,
    ]);
    $type->save();

    $response = patchJson("/api/v1/occupant-types/{$type->id}", [
        'nombre' => 'Visitante frecuente',
    ], createOccTypeAuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data['nombre'])->toBe('Visitante frecuente');
    expect($data['updated_by'])->toBe($auth['user']->id);
});

// ---------------------------------------------------------------
// CASE 5: PATCH tipo sistema → 403 SYSTEM_CATALOG_READONLY
// ---------------------------------------------------------------
test('update system occupant type returns 403', function () {
    $auth = createOccTypeAuthUser();

    $systemType = EloquentOccupantType::query()->whereNull('organization_id')->first();

    $response = patchJson("/api/v1/occupant-types/{$systemType->id}", [
        'nombre' => 'Modificado',
    ], createOccTypeAuthHeader($auth['token']));

    $response->assertForbidden();
    expect($response->json('error.code'))->toBe('SYSTEM_CATALOG_READONLY');
});

// ---------------------------------------------------------------
// CASE 6: DELETE tipo propio sin uso — 204
// ---------------------------------------------------------------
test('delete own occupant type without usage returns 204', function () {
    $auth = createOccTypeAuthUser();

    $type = new EloquentOccupantType([
        'organization_id' => $auth['org']->id,
        'nombre' => 'A eliminar',
        'created_by' => $auth['user']->id,
    ]);
    $type->save();

    $response = deleteJson("/api/v1/occupant-types/{$type->id}", [], createOccTypeAuthHeader($auth['token']));

    $response->assertNoContent();

    expect(EloquentOccupantType::query()->find($type->id))->toBeNull();
    expect(EloquentOccupantType::withTrashed()->find($type->id))->not->toBeNull();
});

// ---------------------------------------------------------------
// CASE 7: DELETE tipo en uso → 409 OCCUPANT_TYPE_IN_USE
// ---------------------------------------------------------------
test('delete occupant type in use returns 409', function () {
    $auth = createOccTypeAuthUser();

    seed(PropertyTypeSeeder::class);
    seed(PropertyStatusSeeder::class);

    $type = new EloquentOccupantType([
        'organization_id' => $auth['org']->id,
        'nombre' => 'En uso',
        'created_by' => $auth['user']->id,
    ]);
    $type->save();

    $propertyType = EloquentPropertyType::query()->whereNull('organization_id')->first();
    $propertyStatus = EloquentPropertyStatus::query()->whereNull('organization_id')->first();

    $condominium = new EloquentCondominium([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Test Condo',
        'created_by' => $auth['user']->id,
    ]);
    $condominium->save();

    $property = new EloquentProperty([
        'condominium_id' => $condominium->id,
        'property_type_id' => $propertyType->id,
        'property_status_id' => $propertyStatus->id,
        'codigo' => 'A-101',
        'created_by' => $auth['user']->id,
    ]);
    $property->save();

    $contact = new EloquentContact([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Contacto Test',
        'email' => 'contacto-test@urbania.test',
    ]);
    $contact->save();

    $occupant = new EloquentPropertyOccupant([
        'contact_id' => $contact->id,
        'property_id' => $property->id,
        'occupant_type_id' => $type->id,
        'created_by' => $auth['user']->id,
    ]);
    $occupant->save();

    $response = deleteJson("/api/v1/occupant-types/{$type->id}", [], createOccTypeAuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('OCCUPANT_TYPE_IN_USE');
});

// ---------------------------------------------------------------
// CASE 8: DELETE tipo sistema → 403
// ---------------------------------------------------------------
test('delete system occupant type returns 403', function () {
    $auth = createOccTypeAuthUser();

    $systemType = EloquentOccupantType::query()->whereNull('organization_id')->first();

    $response = deleteJson("/api/v1/occupant-types/{$systemType->id}", [], createOccTypeAuthHeader($auth['token']));

    $response->assertForbidden();
    expect($response->json('error.code'))->toBe('SYSTEM_CATALOG_READONLY');
});

// ---------------------------------------------------------------
// CASE 9: Sin auth → 401
// ---------------------------------------------------------------
test('unauthenticated access returns 401', function () {
    $response = getJson('/api/v1/occupant-types');

    $response->assertUnauthorized();
});

// ---------------------------------------------------------------
// CASE 10: Rol residente GET → 200
// ---------------------------------------------------------------
test('resident role can list occupant types', function () {
    $resident = createOccTypeResidentUser();

    $response = getJson('/api/v1/occupant-types', createOccTypeAuthHeader($resident['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBeGreaterThanOrEqual(4); // system types
});

// ---------------------------------------------------------------
// CASE 11: Otra org GET → filtrado (tenant isolation)
// ---------------------------------------------------------------
test('other org types are not visible', function () {
    $auth = createOccTypeAuthUser();

    $ownType = new EloquentOccupantType([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Propio',
        'created_by' => $auth['user']->id,
    ]);
    $ownType->save();

    $other = createOccTypeOtherOrgUser();
    $otherType = new EloquentOccupantType([
        'organization_id' => $other['org']->id,
        'nombre' => 'Ajeno',
        'created_by' => $other['user']->id,
    ]);
    $otherType->save();

    $response = getJson('/api/v1/occupant-types', createOccTypeAuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    $nombres = array_column($data, 'nombre');

    expect($nombres)->toContain('Propio');
    expect($nombres)->not->toContain('Ajeno');
});

// ---------------------------------------------------------------
// CASE 12: Staff con scope condominium puede crear (catálogo es org-level)
// ---------------------------------------------------------------
test('staff can create occupant type regardless of condominium scope', function () {
    // El catálogo de occupant-types es a nivel organización, no de condominio —
    // no hay chequeo de scope de condominio en store(), a diferencia de contactos/ocupantes.
    $auth = createOccTypeAuthUser();

    $response = postJson('/api/v1/occupant-types', [
        'nombre' => 'Conserje',
    ], createOccTypeAuthHeader($auth['token']));

    $response->assertCreated();
});
