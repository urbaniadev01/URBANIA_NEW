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

function generatePropStatusAccessToken(User $user): string
{
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function createPropStatusTestOrg(string $name = 'Urbania Test Org'): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => $name]);
    $org->save();

    return $org;
}

function createPropStatusTestUser(EloquentOrganization $org, string $email = 'test@urbania.test', string $estado = 'active'): User
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

function createPropStatusAuthUser(string $estado = 'active'): array
{
    $org = createPropStatusTestOrg();
    $user = createPropStatusTestUser($org, 'admin@urbania.test', $estado);

    return ['org' => $org, 'user' => $user, 'token' => generatePropStatusAccessToken($user)];
}

function createPropStatusAuthHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

// ---------------------------------------------------------------
// CASE 10: GET /property-statuses — 200 + lista (sistema + org)
// ---------------------------------------------------------------
test('list property statuses returns system statuses plus org statuses', function () {
    $auth = createPropStatusAuthUser();

    // Create a custom status for this org
    $customStatus = new EloquentPropertyStatus([
        'organization_id' => $auth['org']->id,
        'nombre' => 'Reservado',
        'descripcion' => 'Unidad reservada',
        'created_by' => $auth['user']->id,
    ]);
    $customStatus->save();

    $response = getJson('/api/v1/property-statuses', createPropStatusAuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toBeArray();

    // Should include 5 system statuses + 1 custom = 6
    expect(count($data))->toBeGreaterThanOrEqual(6);

    // System statuses should be present
    $nombres = array_column($data, 'nombre');
    expect($nombres)->toContain('Disponible');
    expect($nombres)->toContain('Reservado');
});

// ---------------------------------------------------------------
// CASE 11: POST /property-statuses — 201 + created_by
// ---------------------------------------------------------------
test('create property status returns 201 with created_by', function () {
    $auth = createPropStatusAuthUser();

    $response = postJson('/api/v1/property-statuses', [
        'nombre' => 'Pre-venta',
        'descripcion' => 'Unidad en pre-venta',
    ], createPropStatusAuthHeader($auth['token']));

    $response->assertCreated();
    $data = $response->json('data');
    expect($data['nombre'])->toBe('Pre-venta');
    expect($data['organization_id'])->toBe($auth['org']->id);
    expect($data['created_by'])->toBe($auth['user']->id);
});

// ---------------------------------------------------------------
// CASE 12: PATCH estado sistema → 403
// ---------------------------------------------------------------
test('update system property status returns 403', function () {
    $auth = createPropStatusAuthUser();

    $systemStatus = EloquentPropertyStatus::query()
        ->whereNull('organization_id')
        ->first();

    $response = patchJson("/api/v1/property-statuses/{$systemStatus->id}", [
        'nombre' => 'Modificado',
    ], createPropStatusAuthHeader($auth['token']));

    $response->assertForbidden();
    expect($response->json('error.code'))->toBe('SYSTEM_CATALOG_READONLY');
});

// ---------------------------------------------------------------
// CASE 13: DELETE estado en uso → 409
// ---------------------------------------------------------------
test('delete property status in use returns 409', function () {
    $auth = createPropStatusAuthUser();

    $status = new EloquentPropertyStatus([
        'organization_id' => $auth['org']->id,
        'nombre' => 'En uso',
        'created_by' => $auth['user']->id,
    ]);
    $status->save();

    // Get a system type to use as FK
    $type = EloquentPropertyType::query()
        ->whereNull('organization_id')
        ->first();

    // Create a condominium and property referencing this status
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
        'codigo' => 'B-202',
        'created_by' => $auth['user']->id,
    ]);
    $property->save();

    $response = deleteJson("/api/v1/property-statuses/{$status->id}", [], createPropStatusAuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('PROPERTY_STATUS_IN_USE');
});

// ---------------------------------------------------------------
// CASE 16: POST estado duplicado → 409
// ---------------------------------------------------------------
test('duplicate property status name returns 409', function () {
    $auth = createPropStatusAuthUser();

    // Create first
    postJson('/api/v1/property-statuses', [
        'nombre' => 'Pre-venta',
        'descripcion' => 'Pre-venta',
    ], createPropStatusAuthHeader($auth['token']));

    // Duplicate with different case
    $response = postJson('/api/v1/property-statuses', [
        'nombre' => 'PRE-VENTA',
        'descripcion' => 'Otra pre-venta',
    ], createPropStatusAuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('PROPERTY_STATUS_NAME_DUPLICATE');
});
