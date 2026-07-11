<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CobranzaPermissionsSeeder;
use Database\Seeders\PropertyStatusSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Database\Seeders\RbacDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Authorization\Infrastructure\Models\EloquentPermission;
use Urbania\Authorization\Infrastructure\Models\EloquentRole;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;
use Urbania\Billing\Application\Jobs\RunBillingPeriodJob;
use Urbania\Billing\Infrastructure\Models\EloquentBillingPeriod;
use Urbania\Billing\Infrastructure\Models\EloquentBillingRun;
use Urbania\Billing\Infrastructure\Models\EloquentChargeConcept;
use Urbania\Billing\Infrastructure\Models\EloquentInvoice;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyCoefficient;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyStatus;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyType;
use Urbania\Shared\JWT\JwtService;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

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
    seed(PropertyTypeSeeder::class);
    seed(PropertyStatusSeeder::class);
});

// ---------------------------------------------------------------
// Helpers (bp prefix)
// ---------------------------------------------------------------

function bpToken(User $user): string
{
    app()->forgetInstance(JwtService::class);

    /** @var JwtService $jwtService */
    $jwtService = app(JwtService::class);

    return $jwtService->issueAccessToken((string) $user->id);
}

function bpHeader(string $token): array
{
    return ['Authorization' => 'Bearer '.$token];
}

function bpUser(EloquentOrganization $org, string $email): User
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

/**
 * Admin con scope organization — tiene todos los permisos de cobranza asignados por
 * CobranzaPermissionsSeeder (billing.ver, conceptos.*, periodos.ver, facturacion.ejecutar).
 */
function bpAdmin(): array
{
    $org = new EloquentOrganization(['nombre' => 'BP Org']);
    $org->save();

    $user = bpUser($org, 'admin-bp@urbania.test');
    $adminRole = EloquentRole::where('name', 'admin')->first();

    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $adminRole->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    $condo = new EloquentCondominium(['organization_id' => $org->id, 'nombre' => 'Conjunto BP']);
    $condo->save();

    return ['org' => $org, 'user' => $user, 'condo' => $condo, 'token' => bpToken($user)];
}

/**
 * Usuario con un rol ad-hoc que tiene solo los permisos indicados (scope organization).
 */
function bpUserWithPermissions(EloquentOrganization $org, string $email, string $roleName, array $permissions): array
{
    $user = bpUser($org, $email);

    $role = EloquentRole::create(['name' => $roleName, 'description' => 'Rol de prueba '.$roleName]);
    $ids = EloquentPermission::whereIn('name', $permissions)->pluck('id')->all();
    $role->permissions()->attach($ids);

    EloquentRoleAssignment::create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'scope_type' => 'organization',
        'scope_id' => $org->id,
    ]);

    return ['user' => $user, 'token' => bpToken($user)];
}

function bpProperty(EloquentCondominium $condo, string $codigo, ?float $coeficiente, ?float $areaM2 = null): EloquentProperty
{
    $type = EloquentPropertyType::query()->whereNull('organization_id')->first();
    $status = EloquentPropertyStatus::query()->whereNull('organization_id')->first();

    $property = new EloquentProperty([
        'condominium_id' => $condo->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => $codigo,
        'area_m2' => $areaM2,
    ]);
    $property->save();

    if ($coeficiente !== null) {
        EloquentPropertyCoefficient::create([
            'property_id' => $property->id,
            'tipo' => 'copropiedad',
            'valor' => $coeficiente,
            'vigente_desde' => '2026-01-01',
            'vigente_hasta' => null,
        ]);
    }

    return $property;
}

function bpConcept(EloquentCondominium $condo, string $nombre, string $metodo, float $valorBase, bool $activo = true): EloquentChargeConcept
{
    $concept = EloquentChargeConcept::create([
        'condominium_id' => $condo->id,
        'nombre' => $nombre,
        'tipo' => 'administracion',
        'metodo_calculo' => $metodo,
        'valor_base' => $valorBase,
    ]);

    if (! $activo) {
        $concept->activo = false;
        $concept->save();
    }

    return $concept;
}

function bpPeriod(EloquentCondominium $condo, int $anio = 2026, int $mes = 7, string $estado = 'abierto'): EloquentBillingPeriod
{
    return EloquentBillingPeriod::create([
        'condominium_id' => $condo->id,
        'anio' => $anio,
        'mes' => $mes,
        'estado' => $estado,
    ]);
}

// ---------------------------------------------------------------
// CA 1: POST periodo → 201 abierto
// ---------------------------------------------------------------
test('create billing period returns 201 with estado abierto', function (): void {
    $auth = bpAdmin();

    $response = postJson("/api/v1/condominiums/{$auth['condo']->id}/billing-periods", [
        'anio' => 2026,
        'mes' => 7,
    ], bpHeader($auth['token']));

    $response->assertCreated();
    expect($response->json('data.estado'))->toBe('abierto');
    expect($response->json('data.anio'))->toBe(2026);
    expect($response->json('data.mes'))->toBe(7);
});

// ---------------------------------------------------------------
// CA 2: POST periodo duplicado (anio+mes) → 409
// (la tarjeta decía 422; se usa 409 por consistencia — criterio confirmado por el
// usuario al cerrar COBRANZA-B02, ver LOCK-COBRANZA-03)
// ---------------------------------------------------------------
test('duplicate billing period for the same anio and mes returns 409', function (): void {
    $auth = bpAdmin();
    bpPeriod($auth['condo'], 2026, 7);

    $response = postJson("/api/v1/condominiums/{$auth['condo']->id}/billing-periods", [
        'anio' => 2026,
        'mes' => 7,
    ], bpHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('BILLING_PERIOD_DUPLICATE');
});

// ---------------------------------------------------------------
// CA 3: POST billing-run → 202 inmediato, estado en_proceso, Job encolado
// ---------------------------------------------------------------
test('dispatching a billing run returns 202 immediately and queues the job', function (): void {
    Queue::fake();

    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);
    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);
    bpProperty($auth['condo'], 'A-101', 0.5);

    $response = postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']));

    $response->assertStatus(202);
    expect($response->json('data.estado'))->toBe('en_proceso');
    // `resumen` no se expone mientras la corrida está en_proceso
    expect($response->json('data.resumen'))->toBeNull();

    Queue::assertPushed(RunBillingPeriodJob::class);
});

// ---------------------------------------------------------------
// CA 4 + CA 5 + CA 11: el Job prorratea, omite unidades sin coeficiente,
// puebla resumen y genera una invoice por unidad facturable
// ---------------------------------------------------------------
test('billing run job prorates, skips units without a coefficient and fills resumen', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);

    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);

    // 3 unidades con coeficiente vigente, 2 sin él
    bpProperty($auth['condo'], 'A-101', 0.2500);
    bpProperty($auth['condo'], 'A-102', 0.2500);
    bpProperty($auth['condo'], 'A-103', 0.5000);
    $sinCoef1 = bpProperty($auth['condo'], 'B-201', null);
    $sinCoef2 = bpProperty($auth['condo'], 'B-202', null);

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    // QUEUE_CONNECTION=sync en tests: el Job ya corrió al despacharse.
    $run = EloquentBillingRun::where('billing_period_id', $period->id)->firstOrFail();

    expect($run->estado)->toBe('completado');
    expect($run->resumen['unidades_facturadas'])->toBe(3);
    expect($run->resumen['unidades_omitidas'])->toBe(2);

    $omitidas = collect($run->resumen['detalle_omitidas'])->pluck('property_id')->all();
    expect($omitidas)->toContain($sinCoef1->id);
    expect($omitidas)->toContain($sinCoef2->id);
    expect($run->resumen['detalle_omitidas'][0]['motivo'])->toBe('sin coeficiente vigente');

    // CA 5: una invoice por unidad con coeficiente vigente, ninguna para las omitidas
    expect(EloquentInvoice::where('billing_run_id', $run->id)->count())->toBe(3);
    expect(EloquentInvoice::where('property_id', $sinCoef1->id)->count())->toBe(0);

    // Prorrateo correcto: 1.000.000 * 0.25 = 250.000
    $invoice = EloquentInvoice::where('billing_run_id', $run->id)
        ->whereHas('property', fn ($q) => $q->where('codigo', 'A-101'))
        ->firstOrFail();

    expect((float) $invoice->valor_total)->toBe(250000.0);
    // Factura recién emitida: saldo = total
    expect((float) $invoice->saldo)->toBe(250000.0);

    // R-COB-06: base_calculo es snapshot inmutable del coeficiente usado
    $item = $invoice->invoiceItems()->firstOrFail();
    expect((float) $item->base_calculo)->toBe(0.25);

    // GET de polling expone el resumen
    $polling = getJson("/api/v1/billing-runs/{$run->id}", bpHeader($auth['token']));
    $polling->assertOk();
    expect($polling->json('data.estado'))->toBe('completado');
    expect($polling->json('data.resumen.unidades_facturadas'))->toBe(3);
});

// ---------------------------------------------------------------
// Nota de la tarjeta (verificacion_critica): coeficientes que NO suman 1.0000.
// El prorrateo no debe asumir silenciosamente que la suma es perfecta — cada unidad
// se factura por su propio coeficiente, sin normalizar ni redistribuir el faltante.
// ---------------------------------------------------------------
test('prorating does not silently assume coefficients sum to 1.0', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);

    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);

    // Suma = 0.9000, NO 1.0000 (falta 10%)
    bpProperty($auth['condo'], 'A-101', 0.3000);
    bpProperty($auth['condo'], 'A-102', 0.3000);
    bpProperty($auth['condo'], 'A-103', 0.3000);

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    $run = EloquentBillingRun::where('billing_period_id', $period->id)->firstOrFail();
    expect($run->estado)->toBe('completado');
    expect($run->resumen['unidades_facturadas'])->toBe(3);

    // Cada unidad paga exactamente su coeficiente × valor_base — el 10% faltante NO se
    // redistribuye entre las demás ni se factura a nadie. Total facturado = 900.000,
    // no 1.000.000: la diferencia es visible, no silenciosamente absorbida.
    $invoices = EloquentInvoice::where('billing_run_id', $run->id)->get();

    foreach ($invoices as $invoice) {
        expect((float) $invoice->valor_total)->toBe(300000.0);
    }

    $totalFacturado = $invoices->sum(fn ($i) => (float) $i->valor_total);
    expect($totalFacturado)->toBe(900000.0);
});

// ---------------------------------------------------------------
// CA 6: segunda corrida sobre un periodo ya facturado → 409 (R-COB-09)
// ---------------------------------------------------------------
test('a second billing run for the same period is rejected', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);
    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);
    bpProperty($auth['condo'], 'A-101', 0.5);

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    $response = postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('BILLING_RUN_ALREADY_EXISTS');

    // Solo existe una corrida — no se duplicaron facturas
    expect(EloquentBillingRun::where('billing_period_id', $period->id)->count())->toBe(1);
});

// ---------------------------------------------------------------
// CA 7: cerrar periodo con facturas pendientes → 200 + warnings (R-COB-08-bis)
// ---------------------------------------------------------------
test('closing a period with pending invoices returns 200 with a warning', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);
    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);
    bpProperty($auth['condo'], 'A-101', 0.5);

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    // La factura queda con saldo = valor_total (pendiente)
    $response = patchJson("/api/v1/billing-periods/{$period->id}", [
        'estado' => 'cerrado',
    ], bpHeader($auth['token']));

    $response->assertOk();
    expect($response->json('data.estado'))->toBe('cerrado');

    $warnings = $response->json('warnings');
    expect($warnings)->toHaveCount(1);
    expect($warnings[0]['code'])->toBe('BILLING_PERIOD_HAS_PENDING_INVOICES');
    expect($warnings[0]['detail']['invoices_pendientes'])->toBe(1);
});

// ---------------------------------------------------------------
// CA 8: cerrar periodo sin facturas pendientes → 200 sin warnings
// ---------------------------------------------------------------
test('closing a period with all invoices paid returns 200 without warnings', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);
    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);
    bpProperty($auth['condo'], 'A-101', 0.5);

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    // Simular pago total (COBRANZA-B05 hará esto de verdad vía payment_allocations)
    EloquentInvoice::where('billing_period_id', $period->id)->update(['saldo' => 0]);

    $response = patchJson("/api/v1/billing-periods/{$period->id}", [
        'estado' => 'cerrado',
    ], bpHeader($auth['token']));

    $response->assertOk();
    expect($response->json('data.estado'))->toBe('cerrado');
    expect($response->json('warnings'))->toBeNull();
});

// ---------------------------------------------------------------
// CA 9: usuario con periodos.ver (sin facturacion.ejecutar) → POST billing-run 403
// ---------------------------------------------------------------
test('user with only cobranza.periodos.ver cannot dispatch a billing run', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);

    $viewer = bpUserWithPermissions(
        $auth['org'],
        'viewer-bp@urbania.test',
        'auxiliar_periodos_test',
        ['cobranza.periodos.ver'],
    );

    $response = postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($viewer['token']));

    $response->assertStatus(403);
    expect($response->json('error.code'))->toBe('PERMISSION_DENIED');

    // Control: el mismo usuario SÍ puede ver el periodo y sus corridas
    getJson("/api/v1/billing-periods/{$period->id}", bpHeader($viewer['token']))->assertOk();
    getJson("/api/v1/billing-periods/{$period->id}/billing-runs", bpHeader($viewer['token']))->assertOk();
});

// ---------------------------------------------------------------
// CA 10: summary del periodo activo usa billing.ver (no cobranza.periodos.ver)
// ---------------------------------------------------------------
test('active period summary is accessible with only billing.ver', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);
    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);
    bpProperty($auth['condo'], 'A-101', 0.4);
    bpProperty($auth['condo'], 'A-102', 0.6);

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    // Usuario con SOLO billing.ver — el permiso "de entrada" que DASHBOARD usa
    $dashUser = bpUserWithPermissions(
        $auth['org'],
        'dash-bp@urbania.test',
        'dashboard_only_test',
        ['billing.ver'],
    );

    $response = getJson(
        "/api/v1/condominiums/{$auth['condo']->id}/billing-periods/active/summary",
        bpHeader($dashUser['token']),
    );

    $response->assertOk();
    expect($response->json('data.billing_period.id'))->toBe($period->id);
    expect($response->json('data.totales.invoices_total'))->toBe(2);
    // Comparación numérica: un importe redondo se serializa a JSON sin parte decimal
    // (1000000, no 1000000.0) — para un cliente JS/TS ambos son el mismo `number`.
    expect((float) $response->json('data.totales.valor_facturado'))->toBe(1000000.0);
    expect((float) $response->json('data.totales.saldo_pendiente'))->toBe(1000000.0);
    expect($response->json('data.totales.invoices_pendientes'))->toBe(2);
    expect($response->json('data.totales.invoices_pagadas'))->toBe(0);

    // Ese mismo usuario NO puede ver el listado de periodos (requiere cobranza.periodos.ver)
    getJson("/api/v1/condominiums/{$auth['condo']->id}/billing-periods", bpHeader($dashUser['token']))
        ->assertStatus(403);
});

// ---------------------------------------------------------------
// Summary de un periodo específico
// ---------------------------------------------------------------
test('period summary reflects paid invoices', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);
    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);
    bpProperty($auth['condo'], 'A-101', 0.5);
    bpProperty($auth['condo'], 'A-102', 0.5);

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    // Una de las dos facturas se paga por completo
    $invoice = EloquentInvoice::where('billing_period_id', $period->id)->first();
    $invoice->saldo = 0;
    $invoice->save();

    $response = getJson("/api/v1/billing-periods/{$period->id}/summary", bpHeader($auth['token']));

    $response->assertOk();
    expect($response->json('data.totales.invoices_total'))->toBe(2);
    expect($response->json('data.totales.invoices_pagadas'))->toBe(1);
    expect($response->json('data.totales.invoices_pendientes'))->toBe(1);
    expect((float) $response->json('data.totales.valor_facturado'))->toBe(1000000.0);
    expect((float) $response->json('data.totales.saldo_pendiente'))->toBe(500000.0);
    expect((float) $response->json('data.totales.valor_recaudado'))->toBe(500000.0);
});

// ---------------------------------------------------------------
// Conceptos: `fijo`, `por_area` y `manual` (R-COB-07)
// ---------------------------------------------------------------
test('billing run applies fijo and por_area concepts and skips manual ones', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);

    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);
    bpConcept($auth['condo'], 'Vigilancia', 'fijo', 50000);
    bpConcept($auth['condo'], 'Aseo por área', 'por_area', 1000);
    // R-COB-07: los conceptos manuales NO entran en la corrida
    bpConcept($auth['condo'], 'Reparación puntual', 'manual', 99999);
    // Un concepto inactivo tampoco
    bpConcept($auth['condo'], 'Concepto viejo', 'fijo', 77777, activo: false);

    bpProperty($auth['condo'], 'A-101', 0.5, areaM2: 80.0);

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    $invoice = EloquentInvoice::where('billing_period_id', $period->id)->firstOrFail();

    // coeficiente: 1.000.000 * 0.5 = 500.000
    // fijo:                            50.000
    // por_area: 1.000 * 80 =           80.000
    // manual + inactivo:                    0
    expect((float) $invoice->valor_total)->toBe(630000.0);
    expect($invoice->invoiceItems()->count())->toBe(3);

    $conceptos = $invoice->invoiceItems()->pluck('descripcion')->all();
    expect($conceptos)->not->toContain('Reparación puntual');
    expect($conceptos)->not->toContain('Concepto viejo');
});

// ---------------------------------------------------------------
// Tenant isolation / anti-enumeración
// ---------------------------------------------------------------
test('billing period from another organization returns 404', function (): void {
    $auth = bpAdmin();

    $otherOrg = new EloquentOrganization(['nombre' => 'Other BP Org']);
    $otherOrg->save();
    $otherCondo = new EloquentCondominium(['organization_id' => $otherOrg->id, 'nombre' => 'Ajeno']);
    $otherCondo->save();
    $foreignPeriod = bpPeriod($otherCondo);

    $response = getJson("/api/v1/billing-periods/{$foreignPeriod->id}", bpHeader($auth['token']));

    $response->assertStatus(404);
    expect($response->json('error.code'))->toBe('BILLING_PERIOD_NOT_FOUND');
});

test('unauthenticated access returns 401', function (): void {
    $auth = bpAdmin();

    getJson("/api/v1/condominiums/{$auth['condo']->id}/billing-periods")->assertUnauthorized();
});

// ===============================================================
// Regresiones del verify-council — las tres rutas de DOBLE FACTURACIÓN
//
// El council encontró que `billing_runs.estado` no era un guard confiable porque se
// escribía fuera de la transacción que commitea las facturas. Ninguno de los 14 tests
// originales podía detectarlo: con QUEUE_CONNECTION=sync el job corre inline y la
// ventana desaparece. Estos tests atacan el Job directamente, sin pasar por el driver
// de cola, que es la única forma de ejercitar esos caminos.
// ===============================================================

// ---------------------------------------------------------------
// RUTA 1 — Redelivery: la cola reentrega un job cuyo run sigue `en_proceso` porque el
// worker murió después del commit. El guard lo dejaba pasar y re-prorrateaba entero.
// ---------------------------------------------------------------
test('re-running the job for an already-billed period does not duplicate invoices', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);
    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);
    bpProperty($auth['condo'], 'A-101', 0.5);
    bpProperty($auth['condo'], 'A-102', 0.5);

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    $run = EloquentBillingRun::where('billing_period_id', $period->id)->firstOrFail();
    expect($run->estado)->toBe('completado');
    expect(EloquentInvoice::where('billing_period_id', $period->id)->count())->toBe(2);

    // Simular la redelivery: la cola vuelve a entregar el MISMO job. En producción esto
    // pasa si el worker muere entre el commit y el ack. Se fuerza el run de vuelta a
    // `en_proceso` para reproducir el estado exacto que veía el guard.
    $run->estado = 'en_proceso';
    $run->save();

    (new RunBillingPeriodJob((string) $run->id))->handle();

    // El invariante de BD (`invoices_period_property_unique`) + el re-chequeo bajo lock
    // impiden el segundo juego de facturas.
    expect(EloquentInvoice::where('billing_period_id', $period->id)->count())->toBe(2);
});

// ---------------------------------------------------------------
// RUTA 2 — Un segundo run `en_proceso` (creado antes del fix del controller, o por una
// carrera) NO debe poder facturar un periodo que otra corrida ya completó.
// ---------------------------------------------------------------
test('a second run cannot bill a period already completed by another run', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);
    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);
    bpProperty($auth['condo'], 'A-101', 0.5);
    bpProperty($auth['condo'], 'A-102', 0.5);

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    expect(EloquentInvoice::where('billing_period_id', $period->id)->count())->toBe(2);

    // Se inyecta a mano una segunda corrida `en_proceso` — el estado que dos POST
    // concurrentes producían antes del fix — y se la ejecuta.
    $rogue = EloquentBillingRun::create([
        'billing_period_id' => $period->id,
        'ejecutado_por' => $auth['user']->id,
        'fecha' => now(),
        'estado' => 'en_proceso',
    ]);

    (new RunBillingPeriodJob((string) $rogue->id))->handle();

    // Cero facturas nuevas, y la corrida rebelde queda `fallido` sin datos a medias.
    expect(EloquentInvoice::where('billing_period_id', $period->id)->count())->toBe(2);
    expect($rogue->fresh()->estado)->toBe('fallido');
});

// ---------------------------------------------------------------
// RUTA 3 — `fallido` debe significar "no se escribió nada". Antes, un fallo tras el
// commit dejaba las facturas persistidas con el run en `fallido`, y como un run
// `fallido` no bloquea uno nuevo, el operador redisparaba y duplicaba.
// ---------------------------------------------------------------
test('a failed run leaves no invoices behind', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);
    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);
    bpProperty($auth['condo'], 'A-101', 0.5);

    // Corrida ya completada para el periodo (sus facturas son las legítimas).
    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    $facturasLegitimas = EloquentInvoice::where('billing_period_id', $period->id)->count();
    expect($facturasLegitimas)->toBe(1);

    // Una corrida que va a fallar (el periodo ya está facturado).
    $rogue = EloquentBillingRun::create([
        'billing_period_id' => $period->id,
        'ejecutado_por' => $auth['user']->id,
        'fecha' => now(),
        'estado' => 'en_proceso',
    ]);

    (new RunBillingPeriodJob((string) $rogue->id))->handle();

    $rogue->refresh();
    expect($rogue->estado)->toBe('fallido');
    // El conteo no se movió: `fallido` = no se escribió nada.
    expect(EloquentInvoice::where('billing_period_id', $period->id)->count())->toBe($facturasLegitimas);

    // Y el error NO filtra internals (SQL/bindings) por API — solo código + trace_id.
    expect($rogue->resumen['error']['code'])->toBe('BILLING_RUN_FAILED');
    expect($rogue->resumen['error'])->toHaveKey('trace_id');
    expect($rogue->resumen['error'])->not->toHaveKey('message');
});

// ---------------------------------------------------------------
// El hook failed() de la cola marca el run como fallido — sin él, un worker muerto
// dejaba el run `en_proceso` para siempre y el periodo imposible de facturar.
// ---------------------------------------------------------------
test('failed() hook marks a stuck run as fallido so the period is not blocked forever', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);

    $run = EloquentBillingRun::create([
        'billing_period_id' => $period->id,
        'ejecutado_por' => $auth['user']->id,
        'fecha' => now(),
        'estado' => 'en_proceso',
    ]);

    // Lo que hace la cola cuando el worker muere por timeout/OOM.
    (new RunBillingPeriodJob((string) $run->id))->failed(new RuntimeException('worker killed'));

    expect($run->fresh()->estado)->toBe('fallido');

    // Y con el run liberado, el periodo vuelve a ser facturable (no queda un 409 eterno).
    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);
});

// ---------------------------------------------------------------
// Sub-facturación silenciosa: un concepto `por_area` sobre una unidad sin `area_m2` se
// omitía sin dejar rastro — factura bien formada, total menor, indistinguible por query.
// ---------------------------------------------------------------
test('a concept skipped for a unit is recorded in resumen.conceptos_omitidos', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);

    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);
    bpConcept($auth['condo'], 'Aseo por área', 'por_area', 1000);

    $conArea = bpProperty($auth['condo'], 'A-101', 0.5, areaM2: 80.0);
    $sinArea = bpProperty($auth['condo'], 'A-102', 0.5, areaM2: null);

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    $run = EloquentBillingRun::where('billing_period_id', $period->id)->firstOrFail();

    // Ambas se facturan, pero la que no tiene área paga menos...
    $facturaConArea = EloquentInvoice::where('property_id', $conArea->id)->firstOrFail();
    $facturaSinArea = EloquentInvoice::where('property_id', $sinArea->id)->firstOrFail();

    expect((float) $facturaConArea->valor_total)->toBe(580000.0); // 500.000 + 80.000
    expect((float) $facturaSinArea->valor_total)->toBe(500000.0); // solo administración

    // ...y esa diferencia queda AUDITADA, no silenciosa.
    $omitidos = $run->resumen['conceptos_omitidos'];
    expect($omitidos)->toHaveCount(1);
    expect($omitidos[0]['property_id'])->toBe($sinArea->id);
    expect($omitidos[0]['motivo'])->toBe('la unidad no tiene área registrada');
});

// ---------------------------------------------------------------
// R-COB-10: la corrida deja el periodo en `facturado` (no había ninguna aserción de esto)
// ---------------------------------------------------------------
test('a completed billing run leaves the period in estado facturado', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);
    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);
    bpProperty($auth['condo'], 'A-101', 0.5);

    expect($period->estado)->toBe('abierto');

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    expect($period->fresh()->estado)->toBe('facturado');
});

// ---------------------------------------------------------------
// Redondeo real: coeficientes con decimal periódico (el test original usaba 0.30, exacto
// en 2 decimales, que no ejercita el redondeo).
// ---------------------------------------------------------------
test('prorating rounds each item to cents and valor_total equals the sum of its items', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo']);

    bpConcept($auth['condo'], 'Administración', 'coeficiente', 1000000);

    // 0.3333 × 1.000.000 = 333.300,00 exacto en el 4º decimal del coeficiente,
    // pero el producto de un valor_base "feo" sí obliga a redondear.
    bpConcept($auth['condo'], 'Cuota irregular', 'coeficiente', 33333.33);

    bpProperty($auth['condo'], 'A-101', 0.3333);

    postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']))
        ->assertStatus(202);

    $invoice = EloquentInvoice::where('billing_period_id', $period->id)->firstOrFail();
    $items = $invoice->invoiceItems;

    // valor_total DEBE ser exactamente la suma de los ítems ya redondeados — si se
    // redondeara al final, el total no cuadraría con las líneas de la factura.
    $sumaItems = round($items->sum(fn ($i) => (float) $i->valor), 2);
    expect((float) $invoice->valor_total)->toBe($sumaItems);

    // Cada ítem tiene a lo sumo 2 decimales.
    foreach ($items as $item) {
        $valor = (float) $item->valor;
        expect(round($valor, 2))->toBe($valor);
    }
});

// ---------------------------------------------------------------
// Cerrar un periodo ya cerrado → 409 (no estaba cubierto)
// ---------------------------------------------------------------
test('closing an already closed period returns 409', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo'], estado: 'cerrado');

    $response = patchJson("/api/v1/billing-periods/{$period->id}", [
        'estado' => 'cerrado',
    ], bpHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('BILLING_PERIOD_ALREADY_CLOSED');
});

// ---------------------------------------------------------------
// No se puede facturar un periodo cerrado → 409
// ---------------------------------------------------------------
test('a billing run cannot be dispatched for a closed period', function (): void {
    $auth = bpAdmin();
    $period = bpPeriod($auth['condo'], estado: 'cerrado');

    $response = postJson("/api/v1/billing-periods/{$period->id}/billing-runs", [], bpHeader($auth['token']));

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('BILLING_PERIOD_ALREADY_CLOSED');
});
