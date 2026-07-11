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
// Helpers (ContactB03-prefixed to avoid collisions with other test files)
// ---------------------------------------------------------------

function generateContactB03AccessToken(User $user): string
{
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function createContactB03TestOrg(string $name = 'Urbania Contact Org'): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => $name]);
    $org->save();

    return $org;
}

function createContactB03TestUser(EloquentOrganization $org, string $email = 'contact-b03@urbania.test'): User
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

function createContactB03AdminUser(): array
{
    $org = createContactB03TestOrg();
    $user = createContactB03TestUser($org, 'admin-contact-b03@urbania.test');
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateContactB03AccessToken($user)];
}

function createContactB03OtherOrgAdmin(): array
{
    $org = createContactB03TestOrg('Contact Other Org');
    $user = createContactB03TestUser($org, 'other-contact-b03@urbania.test');
    $adminRole = EloquentRole::where('name', 'admin')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => generateContactB03AccessToken($user)];
}

function createContactB03ResidentUser(EloquentOrganization $org): array
{
    $user = createContactB03TestUser($org, 'resident-contact-b03@urbania.test');
    $residentRole = EloquentRole::where('name', 'resident')->first();
    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $residentRole->id,
        'scope_type' => 'unit',
        'scope_id' => (string) Str::orderedUuid(),
    ]);

    return ['user' => $user, 'token' => generateContactB03AccessToken($user)];
}

function createContactB03AuthHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

/**
 * Create a condominium + property + occupant type + occupation for a given contact,
 * so scope/occupation-dependent tests have real data to check against.
 */
function createContactB03Occupation(EloquentOrganization $org, EloquentContact $contact, ?EloquentCondominium $condominium = null): array
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

    $occupantType = EloquentOccupantType::query()->whereNull('organization_id')->first();

    $occupation = new EloquentPropertyOccupant([
        'contact_id' => $contact->id,
        'property_id' => $property->id,
        'occupant_type_id' => $occupantType->id,
    ]);
    $occupation->save();

    return ['condominium' => $condominium, 'property' => $property, 'occupation' => $occupation];
}

// ---------------------------------------------------------------
// CASE 1: GET /contacts — 200 + lista paginada
// ---------------------------------------------------------------
test('list contacts returns org contacts with pagination meta', function () {
    $auth = createContactB03AdminUser();

    $contact = new EloquentContact([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Juan Perez',
        'email' => 'juan@urbania.test',
        'created_by' => $auth['user']->id,
    ]);
    $contact->save();

    $response = getJson('/api/v1/contacts', createContactB03AuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();
    $nombres = array_column($data, 'nombre');
    expect($nombres)->toContain('Juan Perez');
    expect($response->json('meta'))->toHaveKey('next_cursor');
});

// ---------------------------------------------------------------
// CASE 2: search filter
// ---------------------------------------------------------------
test('list contacts filters by search', function () {
    $auth = createContactB03AdminUser();

    foreach (['Juan Perez', 'Maria Lopez'] as $i => $nombre) {
        $c = new EloquentContact(['organization_id' => $auth['org']->id, 'nombre' => $nombre, 'email' => "search-{$i}@urbania.test"]);
        $c->save();
    }

    $response = getJson('/api/v1/contacts?search=Perez', createContactB03AuthHeader($auth['token']));

    $response->assertOk();
    $nombres = array_column($response->json('data'), 'nombre');
    expect($nombres)->toContain('Juan Perez');
    expect($nombres)->not->toContain('Maria Lopez');
});

// ---------------------------------------------------------------
// CASE 3: POST /contacts — 201, siempre sin user_id
// ---------------------------------------------------------------
test('create contact returns 201 without user_id', function () {
    $auth = createContactB03AdminUser();

    $response = postJson('/api/v1/contacts', [
        'nombre' => 'Ana Gomez',
        'email' => 'ana@urbania.test',
        'telefono' => '3001234567',
    ], createContactB03AuthHeader($auth['token']));

    $response->assertCreated();
    $data = $response->json('data');
    expect($data['nombre'])->toBe('Ana Gomez');
    expect($data['organization_id'])->toBe($auth['org']->id);
    expect($data['user_id'])->toBeNull();
    expect($data['created_by'])->toBe($auth['user']->id);
});

// ---------------------------------------------------------------
// CASE 4: POST con user_id en el body → se ignora
// ---------------------------------------------------------------
test('create contact ignores user_id in body', function () {
    $auth = createContactB03AdminUser();

    $response = postJson('/api/v1/contacts', [
        'nombre' => 'Carlos Ruiz',
        'email' => 'carlos@urbania.test',
        'user_id' => (string) Str::orderedUuid(),
    ], createContactB03AuthHeader($auth['token']));

    $response->assertCreated();
    expect($response->json('data.user_id'))->toBeNull();
});

// ---------------------------------------------------------------
// CASE 5: GET /contacts/{id} — 200 + detalle completo
// ---------------------------------------------------------------
test('show contact returns full detail including email and telefono', function () {
    $auth = createContactB03AdminUser();

    $contact = new EloquentContact([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Pedro Diaz',
        'email' => 'pedro@urbania.test',
        'telefono' => '3009876543',
        'created_by' => $auth['user']->id,
    ]);
    $contact->save();

    $response = getJson("/api/v1/contacts/{$contact->id}", createContactB03AuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data['email'])->toBe('pedro@urbania.test');
    expect($data['telefono'])->toBe('3009876543');
});

// ---------------------------------------------------------------
// CASE 6: PATCH /contacts/{id} — 200 + updated_by
// ---------------------------------------------------------------
test('update contact returns 200 with updated_by', function () {
    $auth = createContactB03AdminUser();

    $contact = new EloquentContact(['organization_id' => $auth['org']->id, 'nombre' => 'Old Name', 'email' => 'old-name@urbania.test']);
    $contact->save();

    $response = patchJson("/api/v1/contacts/{$contact->id}", [
        'nombre' => 'New Name',
    ], createContactB03AuthHeader($auth['token']));

    $response->assertOk();
    expect($response->json('data.nombre'))->toBe('New Name');
    expect($response->json('data.updated_by'))->toBe($auth['user']->id);
});

// ---------------------------------------------------------------
// CASE 7: DELETE contacto con ocupaciones → 409
// ---------------------------------------------------------------
test('delete contact with active occupations returns 409', function () {
    $auth = createContactB03AdminUser();

    $contact = new EloquentContact(['organization_id' => $auth['org']->id, 'nombre' => 'Con Ocupacion', 'email' => 'con-ocupacion@urbania.test']);
    $contact->save();

    createContactB03Occupation($auth['org'], $contact);

    $response = deleteJson("/api/v1/contacts/{$contact->id}", [], createContactB03AuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('CONTACT_HAS_OCCUPATIONS');
});

// ---------------------------------------------------------------
// CASE 8: DELETE contacto sin ocupaciones → 204
// ---------------------------------------------------------------
test('delete contact without occupations returns 204', function () {
    $auth = createContactB03AdminUser();

    $contact = new EloquentContact(['organization_id' => $auth['org']->id, 'nombre' => 'Sin Ocupacion', 'email' => 'sin-ocupacion@urbania.test']);
    $contact->save();

    $response = deleteJson("/api/v1/contacts/{$contact->id}", [], createContactB03AuthHeader($auth['token']));

    $response->assertNoContent();
    expect(EloquentContact::query()->find($contact->id))->toBeNull();
});

// ---------------------------------------------------------------
// CASE 9: sin auth → 401
// ---------------------------------------------------------------
test('unauthenticated access returns 401', function () {
    $response = getJson('/api/v1/contacts');

    $response->assertUnauthorized();
});

// ---------------------------------------------------------------
// CASE 10: otra org → 404 (anti-enumeración)
// ---------------------------------------------------------------
test('contact from another organization returns 404', function () {
    $auth = createContactB03AdminUser();
    $other = createContactB03OtherOrgAdmin();

    $otherContact = new EloquentContact(['organization_id' => $other['org']->id, 'nombre' => 'Ajeno', 'email' => 'ajeno@urbania.test']);
    $otherContact->save();

    $response = getJson("/api/v1/contacts/{$otherContact->id}", createContactB03AuthHeader($auth['token']));

    $response->assertStatus(404);
});

// ---------------------------------------------------------------
// CASE 11: staff con scope condominium A, contacto en condominio B → 404
// ---------------------------------------------------------------
test('staff with condominium scope cannot access contact outside scope', function () {
    $org = createContactB03TestOrg('Staff Scope Org');

    $condoA = new EloquentCondominium(['organization_id' => $org->id, 'nombre' => 'Condo A']);
    $condoA->save();
    $condoB = new EloquentCondominium(['organization_id' => $org->id, 'nombre' => 'Condo B']);
    $condoB->save();

    $contactInB = new EloquentContact(['organization_id' => $org->id, 'nombre' => 'Contacto En B', 'email' => 'contacto-en-b@urbania.test']);
    $contactInB->save();
    createContactB03Occupation($org, $contactInB, $condoB);

    $staffUser = createContactB03TestUser($org, 'staff-scope-b03@urbania.test');
    $managerRole = EloquentRole::where('name', 'manager')->first();
    EloquentRoleAssignment::create([
        'user_id' => $staffUser->id,
        'role_id' => $managerRole->id,
        'scope_type' => 'condominium',
        'scope_id' => $condoA->id,
    ]);
    $staffToken = generateContactB03AccessToken($staffUser);

    $response = getJson("/api/v1/contacts/{$contactInB->id}", createContactB03AuthHeader($staffToken));

    $response->assertStatus(404);
});

// ---------------------------------------------------------------
// CASE 12: rol residente (sin scope de gestión) → 403 en GET /contacts
// ---------------------------------------------------------------
test('resident role cannot list contacts', function () {
    $org = createContactB03TestOrg('Resident Only Org');
    $resident = createContactB03ResidentUser($org);

    $response = getJson('/api/v1/contacts', createContactB03AuthHeader($resident['token']));

    $response->assertForbidden();
});

// ---------------------------------------------------------------
// CASE 13: staff con scope condominium ve el listado sin email/telefono
// ---------------------------------------------------------------
test('staff with condominium scope sees contact list without sensitive fields', function () {
    $org = createContactB03TestOrg('Habeas Data Org');

    $condo = new EloquentCondominium(['organization_id' => $org->id, 'nombre' => 'Condo Habeas']);
    $condo->save();

    $contact = new EloquentContact([
        'organization_id' => $org->id,
        'nombre' => 'Contacto Sensible',
        'email' => 'sensible@urbania.test',
        'telefono' => '3000000000',
    ]);
    $contact->save();
    createContactB03Occupation($org, $contact, $condo);

    $staffUser = createContactB03TestUser($org, 'staff-habeas-b03@urbania.test');
    $managerRole = EloquentRole::where('name', 'manager')->first();
    EloquentRoleAssignment::create([
        'user_id' => $staffUser->id,
        'role_id' => $managerRole->id,
        'scope_type' => 'condominium',
        'scope_id' => $condo->id,
    ]);
    $staffToken = generateContactB03AccessToken($staffUser);

    $response = getJson('/api/v1/contacts', createContactB03AuthHeader($staffToken));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
    foreach ($data as $item) {
        expect($item)->not->toHaveKey('email');
        expect($item)->not->toHaveKey('telefono');
    }
});

// ---------------------------------------------------------------
// CASE 13-bis: admin (scope organización) sí ve email/telefono en el listado
// ---------------------------------------------------------------
test('admin with organization scope sees sensitive fields in contact list', function () {
    $auth = createContactB03AdminUser();

    $contact = new EloquentContact([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Contacto Admin',
        'email' => 'admin-visible@urbania.test',
    ]);
    $contact->save();

    $response = getJson('/api/v1/contacts', createContactB03AuthHeader($auth['token']));

    $response->assertOk();
    $match = collect($response->json('data'))->firstWhere('nombre', 'Contacto Admin');
    expect($match)->not->toBeNull();
    expect($match)->toHaveKey('email');
    expect($match['email'])->toBe('admin-visible@urbania.test');
});

// ---------------------------------------------------------------
// CASE 14: GET /me/contact — 200 + propio contacto completo
// ---------------------------------------------------------------
test('me contact show returns own full contact', function () {
    $org = createContactB03TestOrg('Me Contact Org');
    $user = createContactB03TestUser($org, 'self-b03@urbania.test');

    $contact = new EloquentContact([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'nombre' => 'Yo Mismo',
        'email' => 'yo@urbania.test',
        'telefono' => '3001112233',
    ]);
    $contact->save();

    $token = generateContactB03AccessToken($user);

    $response = getJson('/api/v1/me/contact', createContactB03AuthHeader($token));

    $response->assertOk();
    $data = $response->json('data');
    expect($data['nombre'])->toBe('Yo Mismo');
    expect($data['email'])->toBe('yo@urbania.test');
});

// ---------------------------------------------------------------
// CASE 15: PATCH /me/contact — 200 + updated_by = mismo usuario
// ---------------------------------------------------------------
test('me contact update sets updated_by to the same user', function () {
    $org = createContactB03TestOrg('Me Update Org');
    $user = createContactB03TestUser($org, 'self-update-b03@urbania.test');

    $contact = new EloquentContact([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'nombre' => 'Actualizable',
        'email' => 'actualizable@urbania.test',
    ]);
    $contact->save();

    $token = generateContactB03AccessToken($user);

    $response = patchJson('/api/v1/me/contact', [
        'telefono' => '3005556677',
    ], createContactB03AuthHeader($token));

    $response->assertOk();
    expect($response->json('data.telefono'))->toBe('3005556677');
    expect($response->json('data.updated_by'))->toBe($user->id);
});

// ---------------------------------------------------------------
// CASE 16: usuario sin contact asociado (defensivo) → 404
// ---------------------------------------------------------------
test('me contact returns 404 when user has no associated contact', function () {
    $org = createContactB03TestOrg('No Contact Org');
    $user = createContactB03TestUser($org, 'no-contact-b03@urbania.test');
    $token = generateContactB03AccessToken($user);

    $response = getJson('/api/v1/me/contact', createContactB03AuthHeader($token));

    $response->assertStatus(404);
});

// ---------------------------------------------------------------
// CASE 17: GET /contacts/{id}/properties — 200 + unidades del contacto
// ---------------------------------------------------------------
test('get contact properties returns units the contact occupies', function () {
    $auth = createContactB03AdminUser();

    $contact = new EloquentContact(['organization_id' => $auth['org']->id, 'nombre' => 'Multi Unidad', 'email' => 'multi-unidad@urbania.test']);
    $contact->save();

    $first = createContactB03Occupation($auth['org'], $contact);
    createContactB03Occupation($auth['org'], $contact, $first['condominium']);

    $response = getJson("/api/v1/contacts/{$contact->id}/properties", createContactB03AuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect(count($data))->toBe(2);
});
