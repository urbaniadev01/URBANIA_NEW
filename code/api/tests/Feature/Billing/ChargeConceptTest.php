<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CobranzaPermissionsSeeder;
use Database\Seeders\RbacDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Authorization\Infrastructure\Models\EloquentPermission;
use Urbania\Authorization\Infrastructure\Models\EloquentRole;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;
use Urbania\Billing\Infrastructure\Models\EloquentChargeConcept;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
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
    seed(CobranzaPermissionsSeeder::class);
});

// ---------------------------------------------------------------
// Helpers (CC prefix to avoid collisions with other Feature tests)
// ---------------------------------------------------------------

function ccAccessToken(User $user): string
{
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function ccAuthHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

function ccTestOrg(string $name = 'CC Test Org'): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => $name]);
    $org->save();

    return $org;
}

function ccTestUser(EloquentOrganization $org, string $email): User
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

function ccTestCondominium(EloquentOrganization $org, string $nombre = 'Conjunto CC'): EloquentCondominium
{
    $condo = new EloquentCondominium(['organization_id' => $org->id, 'nombre' => $nombre]);
    $condo->save();

    return $condo;
}

/**
 * Usuario con rol 'admin' (org scope) — tiene cobranza.conceptos.ver Y .gestionar
 * (CobranzaPermissionsSeeder los asigna a admin+manager).
 */
function ccAdminUser(): array
{
    $org = ccTestOrg();
    $user = ccTestUser($org, 'admin-cc@urbania.test');
    $adminRole = EloquentRole::where('name', 'admin')->first();

    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['org' => $org, 'user' => $user, 'token' => ccAccessToken($user)];
}

/**
 * Usuario con permiso SOLO cobranza.conceptos.ver (sin .gestionar) — rol ad-hoc
 * distinto de admin/manager, mismo espíritu que R-COB-13 (auxiliar contable).
 */
function ccViewerOnlyUser(EloquentOrganization $org): array
{
    $user = ccTestUser($org, 'viewer-cc@urbania.test');

    $role = EloquentRole::create([
        'name' => 'auxiliar_cc_test',
        'description' => 'Auxiliar de prueba — solo lectura de conceptos de cobro',
    ]);

    $verPermission = EloquentPermission::where('name', 'cobranza.conceptos.ver')->firstOrFail();
    $role->permissions()->attach($verPermission->id);

    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['user' => $user, 'token' => ccAccessToken($user)];
}

/**
 * Usuario sin ningún role_assignment — autenticado, pero sin permisos.
 */
function ccNoPermissionUser(EloquentOrganization $org): array
{
    $user = ccTestUser($org, 'noperm-cc@urbania.test');

    return ['user' => $user, 'token' => ccAccessToken($user)];
}

/**
 * Usuario 'manager' escopeado a un condominio específico (no org-wide).
 */
function ccCondoScopedManager(EloquentOrganization $org, string $condominiumId): array
{
    $user = ccTestUser($org, 'manager-cc@urbania.test');
    $managerRole = EloquentRole::where('name', 'manager')->first();

    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $managerRole->id,
        'scope_type' => 'condominium',
        'scope_id' => $condominiumId,
    ]);

    return ['user' => $user, 'token' => ccAccessToken($user)];
}

// ---------------------------------------------------------------
// CA 1: GET /condominiums/{id}/charge-concepts — 200, lista scopeada
// ---------------------------------------------------------------
test('list charge concepts returns 200 scoped to the condominium', function (): void {
    $auth = ccAdminUser();
    $condo = ccTestCondominium($auth['org']);

    EloquentChargeConcept::create([
        'condominium_id' => $condo->id,
        'nombre' => 'Administración',
        'tipo' => 'administracion',
        'metodo_calculo' => 'coeficiente',
        'valor_base' => 100000,
        'created_by' => $auth['user']->id,
    ]);

    $response = getJson("/api/v1/condominiums/{$condo->id}/charge-concepts", ccAuthHeader($auth['token']));

    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['nombre'])->toBe('Administración');
});

// ---------------------------------------------------------------
// CA 2: POST — 201, tipo=administracion
// ---------------------------------------------------------------
test('create charge concept returns 201', function (): void {
    $auth = ccAdminUser();
    $condo = ccTestCondominium($auth['org']);

    $response = postJson("/api/v1/condominiums/{$condo->id}/charge-concepts", [
        'nombre' => 'Administración',
        'tipo' => 'administracion',
        'metodo_calculo' => 'coeficiente',
        'valor_base' => 100000,
    ], ccAuthHeader($auth['token']));

    $response->assertCreated();
    $data = $response->json('data');
    expect($data['nombre'])->toBe('Administración');
    expect($data['condominium_id'])->toBe($condo->id);
    expect($data['created_by'])->toBe($auth['user']->id);
    expect($data['activo'])->toBeTrue(); // regresión: el POST devolvía activo=null sin ->fresh()
    expect($response->json('warnings'))->toBeNull();
});

// ---------------------------------------------------------------
// CA 3: POST tipo=fondo_imprevistos — 201 + warnings[]
// ---------------------------------------------------------------
test('create fondo_imprevistos charge concept returns warnings', function (): void {
    $auth = ccAdminUser();
    $condo = ccTestCondominium($auth['org']);

    $response = postJson("/api/v1/condominiums/{$condo->id}/charge-concepts", [
        'nombre' => 'Fondo de imprevistos',
        'tipo' => 'fondo_imprevistos',
        'metodo_calculo' => 'coeficiente',
        'valor_base' => 50000,
    ], ccAuthHeader($auth['token']));

    $response->assertCreated();
    $warnings = $response->json('warnings');
    expect($warnings)->toHaveCount(1);
    expect($warnings[0]['code'])->toBe('FONDO_IMPREVISTOS_VALIDACION_PENDIENTE');
});

// ---------------------------------------------------------------
// CA 4: POST nombre duplicado — 409 (ver Notas de la tarjeta: la tarjeta pedía 422,
// se usa 409 por consistencia con el resto del API — PROPERTY_TYPE_NAME_DUPLICATE etc.)
// ---------------------------------------------------------------
test('duplicate charge concept name returns 409', function (): void {
    $auth = ccAdminUser();
    $condo = ccTestCondominium($auth['org']);

    postJson("/api/v1/condominiums/{$condo->id}/charge-concepts", [
        'nombre' => 'Administración',
        'tipo' => 'administracion',
        'metodo_calculo' => 'coeficiente',
        'valor_base' => 100000,
    ], ccAuthHeader($auth['token']));

    $response = postJson("/api/v1/condominiums/{$condo->id}/charge-concepts", [
        'nombre' => 'ADMINISTRACIÓN',
        'tipo' => 'administracion',
        'metodo_calculo' => 'fijo',
        'valor_base' => 200000,
    ], ccAuthHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('CHARGE_CONCEPT_NAME_DUPLICATE');
});

// ---------------------------------------------------------------
// CA 5: POST tipo fuera del set cerrado — 422
// ---------------------------------------------------------------
test('charge concept with tipo outside the closed set returns 422', function (): void {
    $auth = ccAdminUser();
    $condo = ccTestCondominium($auth['org']);

    $response = postJson("/api/v1/condominiums/{$condo->id}/charge-concepts", [
        'nombre' => 'Interés de mora',
        'tipo' => 'interes',
        'metodo_calculo' => 'fijo',
        'valor_base' => 1000,
    ], ccAuthHeader($auth['token']));

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('VALIDATION_ERROR');
    expect(EloquentChargeConcept::query()->count())->toBe(0);
});

// ---------------------------------------------------------------
// CA 6: usuario sin cobranza.conceptos.ver — 403
// ---------------------------------------------------------------
test('user without cobranza.conceptos.ver gets 403 on list', function (): void {
    $org = ccTestOrg();
    $condo = ccTestCondominium($org);
    $noPerm = ccNoPermissionUser($org);

    $response = getJson("/api/v1/condominiums/{$condo->id}/charge-concepts", ccAuthHeader($noPerm['token']));

    $response->assertStatus(403);
    expect($response->json('error.code'))->toBe('PERMISSION_DENIED');
});

// ---------------------------------------------------------------
// CA 7: usuario con .ver (sin .gestionar) — POST 403
// ---------------------------------------------------------------
test('user with only cobranza.conceptos.ver gets 403 on create', function (): void {
    $org = ccTestOrg();
    $condo = ccTestCondominium($org);
    $viewer = ccViewerOnlyUser($org);

    $response = postJson("/api/v1/condominiums/{$condo->id}/charge-concepts", [
        'nombre' => 'Administración',
        'tipo' => 'administracion',
        'metodo_calculo' => 'coeficiente',
        'valor_base' => 100000,
    ], ccAuthHeader($viewer['token']));

    $response->assertStatus(403);
    expect($response->json('error.code'))->toBe('PERMISSION_DENIED');

    // Confirm the viewer CAN still list (segregación ver/gestionar)
    $listResponse = getJson("/api/v1/condominiums/{$condo->id}/charge-concepts", ccAuthHeader($viewer['token']));
    $listResponse->assertOk();
});

// ---------------------------------------------------------------
// CA 8: usuario escopeado a otro condominio — 403 (R-COB-02)
// ---------------------------------------------------------------
test('user scoped to a different condominium gets 403', function (): void {
    $org = ccTestOrg();
    $ownCondo = ccTestCondominium($org, 'Conjunto Propio');
    $otherCondo = ccTestCondominium($org, 'Conjunto Ajeno');

    $manager = ccCondoScopedManager($org, $ownCondo->id);

    $response = getJson("/api/v1/condominiums/{$otherCondo->id}/charge-concepts", ccAuthHeader($manager['token']));

    $response->assertStatus(403);
    expect($response->json('error.code'))->toBe('PERMISSION_DENIED');

    // Confirm access to their own condominium works
    $ownResponse = getJson("/api/v1/condominiums/{$ownCondo->id}/charge-concepts", ccAuthHeader($manager['token']));
    $ownResponse->assertOk();
});

// ---------------------------------------------------------------
// CA 9: DELETE — 204, deleted_at + activo=false
// ---------------------------------------------------------------
test('delete charge concept returns 204 and deactivates it', function (): void {
    $auth = ccAdminUser();
    $condo = ccTestCondominium($auth['org']);

    $concept = EloquentChargeConcept::create([
        'condominium_id' => $condo->id,
        'nombre' => 'A eliminar',
        'tipo' => 'administracion',
        'metodo_calculo' => 'fijo',
        'valor_base' => 1000,
        'created_by' => $auth['user']->id,
    ]);

    $response = deleteJson("/api/v1/charge-concepts/{$concept->id}", [], ccAuthHeader($auth['token']));

    $response->assertNoContent();

    $found = EloquentChargeConcept::withTrashed()->find($concept->id);
    expect($found->deleted_at)->not->toBeNull();
    expect($found->activo)->toBeFalse();
});

// ---------------------------------------------------------------
// CA 10: concepto desactivado no aparece en el listado por defecto
// ---------------------------------------------------------------
test('deactivated concept does not appear in the default listing', function (): void {
    $auth = ccAdminUser();
    $condo = ccTestCondominium($auth['org']);

    $concept = EloquentChargeConcept::create([
        'condominium_id' => $condo->id,
        'nombre' => 'Desactivado',
        'tipo' => 'administracion',
        'metodo_calculo' => 'fijo',
        'valor_base' => 1000,
        'created_by' => $auth['user']->id,
    ]);
    $concept->activo = false;
    $concept->save();
    $concept->delete();

    $response = getJson("/api/v1/condominiums/{$condo->id}/charge-concepts", ccAuthHeader($auth['token']));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(0);
});

// ---------------------------------------------------------------
// Extra: show/update/destroy respetan tenant isolation + PATCH funcional
// ---------------------------------------------------------------
test('show and update work for a concept within the same organization', function (): void {
    $auth = ccAdminUser();
    $condo = ccTestCondominium($auth['org']);

    $concept = EloquentChargeConcept::create([
        'condominium_id' => $condo->id,
        'nombre' => 'Original',
        'tipo' => 'administracion',
        'metodo_calculo' => 'fijo',
        'valor_base' => 1000,
        'created_by' => $auth['user']->id,
    ]);

    $showResponse = getJson("/api/v1/charge-concepts/{$concept->id}", ccAuthHeader($auth['token']));
    $showResponse->assertOk();
    expect($showResponse->json('data.nombre'))->toBe('Original');

    $updateResponse = patchJson("/api/v1/charge-concepts/{$concept->id}", [
        'valor_base' => 2000,
    ], ccAuthHeader($auth['token']));

    $updateResponse->assertOk();
    expect((float) $updateResponse->json('data.valor_base'))->toBe(2000.0);
    expect($updateResponse->json('data.updated_by'))->toBe($auth['user']->id);
});

test('charge concept from another organization returns 404', function (): void {
    $auth = ccAdminUser();
    $otherOrg = ccTestOrg('Other CC Org');
    $otherCondo = ccTestCondominium($otherOrg, 'Otro Conjunto');

    $foreignConcept = EloquentChargeConcept::create([
        'condominium_id' => $otherCondo->id,
        'nombre' => 'Ajeno',
        'tipo' => 'administracion',
        'metodo_calculo' => 'fijo',
        'valor_base' => 1000,
    ]);

    $response = getJson("/api/v1/charge-concepts/{$foreignConcept->id}", ccAuthHeader($auth['token']));

    $response->assertStatus(404);
    expect($response->json('error.code'))->toBe('CHARGE_CONCEPT_NOT_FOUND');
});

test('unauthenticated access returns 401', function (): void {
    $org = ccTestOrg();
    $condo = ccTestCondominium($org);

    $response = getJson("/api/v1/condominiums/{$condo->id}/charge-concepts");

    $response->assertUnauthorized();
});
