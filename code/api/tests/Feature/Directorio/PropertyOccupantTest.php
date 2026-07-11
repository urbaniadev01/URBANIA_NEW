<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\OccupantTypeSeeder;
use Database\Seeders\PropertyStatusSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RbacDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Urbania\Auth\Infrastructure\Models\EloquentContact;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Authorization\Infrastructure\Models\EloquentRole;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;
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
// Setup
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

    seed(RbacDemoSeeder::class);
    seed(PropertyTypeSeeder::class);
    seed(PropertyStatusSeeder::class);
    seed(OccupantTypeSeeder::class);
});

// ---------------------------------------------------------------
// Helpers (OccAssignB04-prefixed to avoid collisions)
// ---------------------------------------------------------------

function generateOccAssignB04AccessToken(User $user): string
{
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function createOccAssignB04TestOrg(string $name = 'Urbania Assign Org'): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => $name]);
    $org->save();

    return $org;
}

function createOccAssignB04TestUser(EloquentOrganization $org, string $email): User
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

function createOccAssignB04AdminUser(): array
{
    $org = createOccAssignB04TestOrg('Urbania Assign Org '.Str::random(6));
    $user = createOccAssignB04TestUser($org, 'admin-assign-b04-'.Str::random(6).'@urbania.test');
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateOccAssignB04AccessToken($user)];
}

function createOccAssignB04AuthHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

/**
 * Create a condominium + unit for an org, ready to attach occupants to.
 */
function createOccAssignB04Unit(EloquentOrganization $org, ?EloquentCondominium $condominium = null): array
{
    $condominium ??= new EloquentCondominium(['organization_id' => $org->id, 'nombre' => 'Condo '.Str::random(6)]);
    if (! $condominium->exists) {
        $condominium->save();
    }

    $propertyType = EloquentPropertyType::query()->whereNull('organization_id')->first();
    $propertyStatus = EloquentPropertyStatus::query()->whereNull('organization_id')->first();

    $property = new EloquentProperty([
        'condominium_id' => $condominium->id,
        'property_type_id' => $propertyType->id,
        'property_status_id' => $propertyStatus->id,
        'codigo' => 'U-'.Str::random(4),
    ]);
    $property->save();

    return ['condominium' => $condominium, 'property' => $property];
}

function createOccAssignB04Contact(EloquentOrganization $org, string $nombre = 'Contacto Test'): EloquentContact
{
    $contact = new EloquentContact([
        'organization_id' => $org->id,
        'nombre' => $nombre,
        'email' => strtolower(str_replace(' ', '.', $nombre)).'-'.Str::random(4).'@urbania.test',
    ]);
    $contact->save();

    return $contact;
}

// ---------------------------------------------------------------
// CASE 1: GET /properties/{id}/occupants — 200 + lista con occupant_type
// ---------------------------------------------------------------
test('list occupants of a unit returns them with occupant_type', function () {
    $auth = createOccAssignB04AdminUser();
    $unit = createOccAssignB04Unit($auth['org']);
    $contact = createOccAssignB04Contact($auth['org']);
    $occupantType = EloquentOccupantType::query()->whereNull('organization_id')->first();

    $occupation = new EloquentPropertyOccupant([
        'contact_id' => $contact->id,
        'property_id' => $unit['property']->id,
        'occupant_type_id' => $occupantType->id,
    ]);
    $occupation->save();

    $response = getJson("/api/v1/properties/{$unit['property']->id}/occupants", createOccAssignB04AuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['contact']['nombre'])->toBe('Contacto Test');
    expect($data[0]['occupant_type']['nombre'])->toBe($occupantType->nombre);
    expect($data[0])->not->toHaveKey('email');
});

// ---------------------------------------------------------------
// CASE 2: POST — 201 + created_by
// ---------------------------------------------------------------
test('assign occupant returns 201 with created_by', function () {
    $auth = createOccAssignB04AdminUser();
    $unit = createOccAssignB04Unit($auth['org']);
    $contact = createOccAssignB04Contact($auth['org']);
    $occupantType = EloquentOccupantType::query()->whereNull('organization_id')->first();

    $response = postJson("/api/v1/properties/{$unit['property']->id}/occupants", [
        'contact_id' => $contact->id,
        'occupant_type_id' => $occupantType->id,
        'es_principal' => false,
    ], createOccAssignB04AuthHeader($auth['token']));

    $response->assertCreated();
    $data = $response->json('data');
    expect($data['contact_id'])->toBe($contact->id);
    expect($data['created_by'])->toBe($auth['user']->id);
});

// ---------------------------------------------------------------
// CASE 3: duplicado → 409
// ---------------------------------------------------------------
test('duplicate assignment returns 409', function () {
    $auth = createOccAssignB04AdminUser();
    $unit = createOccAssignB04Unit($auth['org']);
    $contact = createOccAssignB04Contact($auth['org']);
    $occupantType = EloquentOccupantType::query()->whereNull('organization_id')->first();

    postJson("/api/v1/properties/{$unit['property']->id}/occupants", [
        'contact_id' => $contact->id,
        'occupant_type_id' => $occupantType->id,
    ], createOccAssignB04AuthHeader($auth['token']));

    $response = postJson("/api/v1/properties/{$unit['property']->id}/occupants", [
        'contact_id' => $contact->id,
        'occupant_type_id' => $occupantType->id,
    ], createOccAssignB04AuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('OCCUPANT_ASSIGNMENT_DUPLICATE');
});

// ---------------------------------------------------------------
// CASE 4: mismo contact_id/property_id, occupant_type_id distinto → 201
// ---------------------------------------------------------------
test('same contact can have a different occupant type on the same unit', function () {
    $auth = createOccAssignB04AdminUser();
    $unit = createOccAssignB04Unit($auth['org']);
    $contact = createOccAssignB04Contact($auth['org']);
    $types = EloquentOccupantType::query()->whereNull('organization_id')->limit(2)->get();

    postJson("/api/v1/properties/{$unit['property']->id}/occupants", [
        'contact_id' => $contact->id,
        'occupant_type_id' => $types[0]->id,
    ], createOccAssignB04AuthHeader($auth['token']));

    $response = postJson("/api/v1/properties/{$unit['property']->id}/occupants", [
        'contact_id' => $contact->id,
        'occupant_type_id' => $types[1]->id,
    ], createOccAssignB04AuthHeader($auth['token']));

    $response->assertCreated();
});

// ---------------------------------------------------------------
// CASE 5: contact_id de otra organización → 422
// ---------------------------------------------------------------
test('contact_id from another organization returns 422', function () {
    $auth = createOccAssignB04AdminUser();
    $unit = createOccAssignB04Unit($auth['org']);
    $occupantType = EloquentOccupantType::query()->whereNull('organization_id')->first();

    $otherOrg = createOccAssignB04TestOrg('Other Assign Org');
    $otherContact = createOccAssignB04Contact($otherOrg, 'Contacto Ajeno');

    $response = postJson("/api/v1/properties/{$unit['property']->id}/occupants", [
        'contact_id' => $otherContact->id,
        'occupant_type_id' => $occupantType->id,
    ], createOccAssignB04AuthHeader($auth['token']));

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('VALIDATION_ERROR');
});

// ---------------------------------------------------------------
// CASE 6: es_principal=true desmarca al anterior automáticamente
// ---------------------------------------------------------------
test('marking a new principal unmarks the previous one', function () {
    $auth = createOccAssignB04AdminUser();
    $unit = createOccAssignB04Unit($auth['org']);
    $occupantType = EloquentOccupantType::query()->where('nombre', 'Propietario')->first();

    $firstContact = createOccAssignB04Contact($auth['org'], 'Primer Propietario');
    $firstResponse = postJson("/api/v1/properties/{$unit['property']->id}/occupants", [
        'contact_id' => $firstContact->id,
        'occupant_type_id' => $occupantType->id,
        'es_principal' => true,
    ], createOccAssignB04AuthHeader($auth['token']));
    $firstId = $firstResponse->json('data.id');

    $secondContact = createOccAssignB04Contact($auth['org'], 'Segundo Propietario');
    postJson("/api/v1/properties/{$unit['property']->id}/occupants", [
        'contact_id' => $secondContact->id,
        'occupant_type_id' => $occupantType->id,
        'es_principal' => true,
    ], createOccAssignB04AuthHeader($auth['token']));

    $first = EloquentPropertyOccupant::query()->find($firstId);
    expect($first->es_principal)->toBeFalse();
});

// ---------------------------------------------------------------
// CASE 7: PATCH — 200 + updated_by
// ---------------------------------------------------------------
test('update occupant assignment returns 200 with updated_by', function () {
    $auth = createOccAssignB04AdminUser();
    $unit = createOccAssignB04Unit($auth['org']);
    $contact = createOccAssignB04Contact($auth['org']);
    $types = EloquentOccupantType::query()->whereNull('organization_id')->limit(2)->get();

    $created = postJson("/api/v1/properties/{$unit['property']->id}/occupants", [
        'contact_id' => $contact->id,
        'occupant_type_id' => $types[0]->id,
    ], createOccAssignB04AuthHeader($auth['token']));
    $occupantId = $created->json('data.id');

    $response = patchJson("/api/v1/property-occupants/{$occupantId}", [
        'occupant_type_id' => $types[1]->id,
    ], createOccAssignB04AuthHeader($auth['token']));

    $response->assertOk();
    expect($response->json('data.occupant_type_id'))->toBe($types[1]->id);
    expect($response->json('data.updated_by'))->toBe($auth['user']->id);
});

// ---------------------------------------------------------------
// CASE 8: DELETE — 204
// ---------------------------------------------------------------
test('delete occupant assignment returns 204', function () {
    $auth = createOccAssignB04AdminUser();
    $unit = createOccAssignB04Unit($auth['org']);
    $contact = createOccAssignB04Contact($auth['org']);
    $occupantType = EloquentOccupantType::query()->whereNull('organization_id')->first();

    $created = postJson("/api/v1/properties/{$unit['property']->id}/occupants", [
        'contact_id' => $contact->id,
        'occupant_type_id' => $occupantType->id,
    ], createOccAssignB04AuthHeader($auth['token']));
    $occupantId = $created->json('data.id');

    $response = deleteJson("/api/v1/property-occupants/{$occupantId}", [], createOccAssignB04AuthHeader($auth['token']));

    $response->assertNoContent();
    expect(EloquentPropertyOccupant::query()->find($occupantId))->toBeNull();
});

// ---------------------------------------------------------------
// CASE 9: sin auth → 401
// ---------------------------------------------------------------
test('unauthenticated access returns 401', function () {
    $auth = createOccAssignB04AdminUser();
    $unit = createOccAssignB04Unit($auth['org']);

    $response = getJson("/api/v1/properties/{$unit['property']->id}/occupants");

    $response->assertUnauthorized();
});

// ---------------------------------------------------------------
// CASE 10: unidad de otra org → 404
// ---------------------------------------------------------------
test('unit from another organization returns 404', function () {
    $auth = createOccAssignB04AdminUser();
    $other = createOccAssignB04AdminUser();
    $otherUnit = createOccAssignB04Unit($other['org']);

    $response = getJson("/api/v1/properties/{$otherUnit['property']->id}/occupants", createOccAssignB04AuthHeader($auth['token']));

    $response->assertStatus(404);
});

// ---------------------------------------------------------------
// CASE 11: staff scope condominium A, unidad en condominio B (misma org) → 404
// ---------------------------------------------------------------
test('staff with condominium scope cannot manage occupants outside scope', function () {
    $org = createOccAssignB04TestOrg('Staff Assign Org');

    $condoA = new EloquentCondominium(['organization_id' => $org->id, 'nombre' => 'Condo A']);
    $condoA->save();
    $unitB = createOccAssignB04Unit($org);
    $contact = createOccAssignB04Contact($org);
    $occupantType = EloquentOccupantType::query()->whereNull('organization_id')->first();

    $staffUser = createOccAssignB04TestUser($org, 'staff-assign-b04@urbania.test');
    $managerRole = EloquentRole::where('name', 'manager')->first();
    EloquentRoleAssignment::create([
        'user_id' => $staffUser->id,
        'role_id' => $managerRole->id,
        'scope_type' => 'condominium',
        'scope_id' => $condoA->id,
    ]);
    $staffToken = generateOccAssignB04AccessToken($staffUser);

    $response = postJson("/api/v1/properties/{$unitB['property']->id}/occupants", [
        'contact_id' => $contact->id,
        'occupant_type_id' => $occupantType->id,
    ], createOccAssignB04AuthHeader($staffToken));

    $response->assertStatus(404);
});

// ---------------------------------------------------------------
// CASE 12: rol residente → 403 en POST
// ---------------------------------------------------------------
test('resident role cannot assign occupants', function () {
    $org = createOccAssignB04TestOrg('Resident Assign Org');
    $unit = createOccAssignB04Unit($org);
    $contact = createOccAssignB04Contact($org);
    $occupantType = EloquentOccupantType::query()->whereNull('organization_id')->first();

    $resident = createOccAssignB04TestUser($org, 'resident-assign-b04@urbania.test');
    $residentRole = EloquentRole::where('name', 'resident')->first();
    EloquentRoleAssignment::create([
        'user_id' => $resident->id,
        'role_id' => $residentRole->id,
        'scope_type' => 'unit',
        'scope_id' => $unit['property']->id,
    ]);
    $residentToken = generateOccAssignB04AccessToken($resident);

    $response = postJson("/api/v1/properties/{$unit['property']->id}/occupants", [
        'contact_id' => $contact->id,
        'occupant_type_id' => $occupantType->id,
    ], createOccAssignB04AuthHeader($residentToken));

    $response->assertForbidden();
});

// ---------------------------------------------------------------
// CASE 13: rol residente puede ver el índice de su propia unidad
// ---------------------------------------------------------------
test('resident role can list occupants of their own unit', function () {
    $org = createOccAssignB04TestOrg('Resident View Org');
    $unit = createOccAssignB04Unit($org);
    $contact = createOccAssignB04Contact($org);
    $occupantType = EloquentOccupantType::query()->whereNull('organization_id')->first();

    $occupation = new EloquentPropertyOccupant([
        'contact_id' => $contact->id,
        'property_id' => $unit['property']->id,
        'occupant_type_id' => $occupantType->id,
    ]);
    $occupation->save();

    $resident = createOccAssignB04TestUser($org, 'resident-view-b04@urbania.test');
    $residentRole = EloquentRole::where('name', 'resident')->first();
    EloquentRoleAssignment::create([
        'user_id' => $resident->id,
        'role_id' => $residentRole->id,
        'scope_type' => 'unit',
        'scope_id' => $unit['property']->id,
    ]);
    $residentToken = generateOccAssignB04AccessToken($resident);

    $response = getJson("/api/v1/properties/{$unit['property']->id}/occupants", createOccAssignB04AuthHeader($residentToken));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0])->not->toHaveKey('email');
    expect($data[0])->not->toHaveKey('telefono');
});
