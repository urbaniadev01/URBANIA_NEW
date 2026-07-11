<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\CobranzaPermissionsSeeder;
use Database\Seeders\PropertyStatusSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Urbania\Auth\Infrastructure\Models\EloquentContact;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Authorization\Infrastructure\Models\EloquentPermission;
use Urbania\Billing\Infrastructure\Models\EloquentBillingPeriod;
use Urbania\Billing\Infrastructure\Models\EloquentBillingRun;
use Urbania\Billing\Infrastructure\Models\EloquentChargeConcept;
use Urbania\Billing\Infrastructure\Models\EloquentInvoice;
use Urbania\Billing\Infrastructure\Models\EloquentPaymentReceipt;
use Urbania\Billing\Infrastructure\Models\EloquentPeaceCertificate;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyStatus;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyType;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

function billingTestOrg(): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => 'Billing Test Org']);
    $org->save();

    return $org;
}

function billingTestCondominium(EloquentOrganization $org): EloquentCondominium
{
    $condo = new EloquentCondominium(['organization_id' => $org->id, 'nombre' => 'Condominio COB']);
    $condo->save();

    return $condo;
}

function billingTestProperty(EloquentCondominium $condo, string $codigo = 'B-101'): EloquentProperty
{
    seed(PropertyTypeSeeder::class);
    seed(PropertyStatusSeeder::class);

    $type = EloquentPropertyType::query()->whereNull('organization_id')->first();
    $status = EloquentPropertyStatus::query()->whereNull('organization_id')->first();

    $property = new EloquentProperty([
        'condominium_id' => $condo->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => $codigo,
    ]);
    $property->save();

    return $property;
}

function billingTestUser(EloquentOrganization $org): User
{
    return User::create([
        'organization_id' => $org->id,
        'email' => 'billing-actor-'.Uuid::uuid4()->toString().'@urbania.test',
        'password_hash' => 'irrelevant',
        'estado' => 'active',
    ]);
}

function billingTestChargeConcept(EloquentCondominium $condo): EloquentChargeConcept
{
    return EloquentChargeConcept::create([
        'condominium_id' => $condo->id,
        'nombre' => 'Administración',
        'tipo' => 'administracion',
        'metodo_calculo' => 'coeficiente',
        'valor_base' => 100000,
    ]);
}

function billingTestPeriod(EloquentCondominium $condo): EloquentBillingPeriod
{
    return EloquentBillingPeriod::create([
        'condominium_id' => $condo->id,
        'anio' => 2026,
        'mes' => 7,
    ]);
}

function billingTestRun(EloquentBillingPeriod $period, User $actor, string $estado = 'completado'): EloquentBillingRun
{
    return EloquentBillingRun::create([
        'billing_period_id' => $period->id,
        'ejecutado_por' => $actor->id,
        'fecha' => now(),
        'estado' => $estado,
    ]);
}

// ---------------------------------------------------------------
// Criterion 5: billing_period hasMany billing_runs
// ---------------------------------------------------------------
test('billing period hasMany billing runs relation works', function (): void {
    $org = billingTestOrg();
    $condo = billingTestCondominium($org);
    $actor = billingTestUser($org);
    $period = billingTestPeriod($condo);

    $run = $period->billingRuns()->create([
        'ejecutado_por' => $actor->id,
        'fecha' => now(),
        'estado' => 'en_proceso',
    ]);

    expect($run->billing_period_id)->toBe($period->id);
    expect($period->billingRuns()->count())->toBe(1);
});

// ---------------------------------------------------------------
// Criterion 6: invoice hasMany invoice_items
// ---------------------------------------------------------------
test('invoice hasMany invoice items relation works', function (): void {
    $org = billingTestOrg();
    $condo = billingTestCondominium($org);
    $property = billingTestProperty($condo);
    $actor = billingTestUser($org);
    $period = billingTestPeriod($condo);
    $run = billingTestRun($period, $actor);
    $concept = billingTestChargeConcept($condo);

    $invoice = EloquentInvoice::create([
        'condominium_id' => $condo->id,
        'property_id' => $property->id,
        'billing_period_id' => $period->id,
        'billing_run_id' => $run->id,
        'numero' => 'FAC-0001',
        'fecha_emision' => '2026-07-01',
        'fecha_vencimiento' => '2026-07-15',
        'valor_total' => 100000,
        'saldo' => 100000,
    ]);

    $item = $invoice->invoiceItems()->create([
        'charge_concept_id' => $concept->id,
        'valor' => 100000,
    ]);

    expect($item->invoice_id)->toBe($invoice->id);
    expect($invoice->invoiceItems()->count())->toBe(1);
});

// ---------------------------------------------------------------
// Criterion 7: invoice->property() cross-context read (ADR-002)
// ---------------------------------------------------------------
test('invoice property relation reads the real Properties model (cross-context, ADR-002)', function (): void {
    $org = billingTestOrg();
    $condo = billingTestCondominium($org);
    $property = billingTestProperty($condo);
    $actor = billingTestUser($org);
    $period = billingTestPeriod($condo);
    $run = billingTestRun($period, $actor);

    $invoice = EloquentInvoice::create([
        'condominium_id' => $condo->id,
        'property_id' => $property->id,
        'billing_period_id' => $period->id,
        'billing_run_id' => $run->id,
        'numero' => 'FAC-0002',
        'fecha_emision' => '2026-07-01',
        'fecha_vencimiento' => '2026-07-15',
        'valor_total' => 50000,
        'saldo' => 50000,
    ]);

    $loaded = $invoice->property;

    expect($loaded)->toBeInstanceOf(EloquentProperty::class);
    expect($loaded->id)->toBe($property->id);
});

// ---------------------------------------------------------------
// Criterion 8: FK integrity — nonexistent billing_period_id fails
// ---------------------------------------------------------------
test('billing_runs with a nonexistent billing_period_id fails on FK constraint', function (): void {
    $org = billingTestOrg();
    $actor = billingTestUser($org);

    expect(fn () => EloquentBillingRun::create([
        'billing_period_id' => (string) Str::orderedUuid(),
        'ejecutado_por' => $actor->id,
        'fecha' => now(),
        'estado' => 'en_proceso',
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------
// Criterion 9: second billing_run 'completado' for the same period fails
// (constraint de BD, decisión 7)
// ---------------------------------------------------------------
test('two billing_runs completado for the same period violate the partial unique index', function (): void {
    $org = billingTestOrg();
    $condo = billingTestCondominium($org);
    $actor = billingTestUser($org);
    $period = billingTestPeriod($condo);

    billingTestRun($period, $actor, 'completado');

    expect(fn () => billingTestRun($period, $actor, 'completado'))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------
// Criterion 10: charge_concepts.tipo outside the closed set fails (CHECK)
// ---------------------------------------------------------------
test('charge_concepts rejects a tipo outside the closed set via CHECK constraint', function (): void {
    $org = billingTestOrg();
    $condo = billingTestCondominium($org);

    expect(fn () => EloquentChargeConcept::create([
        'condominium_id' => $condo->id,
        'nombre' => 'Interés de mora',
        'tipo' => 'interes',
        'metodo_calculo' => 'fijo',
        'valor_base' => 1000,
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------
// Criterion 11: payment_receipts.medio outside the closed set fails (CHECK, R-COB-15)
// ---------------------------------------------------------------
test('payment_receipts rejects a medio outside the closed set via CHECK constraint', function (): void {
    $org = billingTestOrg();
    $condo = billingTestCondominium($org);
    $property = billingTestProperty($condo);
    $actor = billingTestUser($org);

    $contact = new EloquentContact([
        'organization_id' => $org->id,
        'nombre' => 'Pagador',
        'email' => 'pagador@urbania.test',
    ]);
    $contact->save();

    expect(fn () => EloquentPaymentReceipt::create([
        'condominium_id' => $condo->id,
        'property_id' => $property->id,
        'contact_id' => $contact->id,
        'valor' => 50000,
        'fecha' => '2026-07-01',
        'medio' => 'pse',
        'created_by' => $actor->id,
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------
// Criterion 12: soft delete fills deleted_at
// ---------------------------------------------------------------
test('charge concept soft delete fills deleted_at', function (): void {
    $org = billingTestOrg();
    $condo = billingTestCondominium($org);
    $concept = billingTestChargeConcept($condo);

    expect($concept->deleted_at)->toBeNull();
    $concept->delete();

    expect($concept->fresh()->deleted_at)->not->toBeNull();
    expect(EloquentChargeConcept::find($concept->id))->toBeNull();
    expect(EloquentChargeConcept::withTrashed()->find($concept->id))->not->toBeNull();
});

// ---------------------------------------------------------------
// Criterion 13: id is a valid UUID v7
// ---------------------------------------------------------------
test('charge concept id is a valid UUID v7', function (): void {
    $org = billingTestOrg();
    $condo = billingTestCondominium($org);
    $concept = billingTestChargeConcept($condo);

    expect(Uuid::isValid($concept->id))->toBeTrue();
    $version = (int) substr($concept->id, 14, 1);
    expect($version)->toEqual(7);
});

// ---------------------------------------------------------------
// Criterion 14: billing_runs without ejecutado_por fails (NOT NULL, decisión 4)
// ---------------------------------------------------------------
test('billing_runs without ejecutado_por fails — actor is mandatory', function (): void {
    $org = billingTestOrg();
    $condo = billingTestCondominium($org);
    $period = billingTestPeriod($condo);

    expect(fn () => EloquentBillingRun::create([
        'billing_period_id' => $period->id,
        'fecha' => now(),
        'estado' => 'en_proceso',
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------
// Criterion 15: peace_certificates without emitido_por fails (NOT NULL, decisión 4)
// ---------------------------------------------------------------
test('peace_certificates without emitido_por fails — actor is mandatory', function (): void {
    $org = billingTestOrg();
    $condo = billingTestCondominium($org);
    $property = billingTestProperty($condo);

    expect(fn () => EloquentPeaceCertificate::create([
        'condominium_id' => $condo->id,
        'property_id' => $property->id,
        'numero' => 'PYS-0001',
        'fecha' => '2026-07-01',
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------
// Criterion 16: CobranzaPermissionsSeeder — billing.ver exists exactly once
// (see BillingMigrationTest for the full seeder coverage)
// ---------------------------------------------------------------
test('billing.ver permission exists exactly once after seeding', function (): void {
    seed(CobranzaPermissionsSeeder::class);

    expect(EloquentPermission::where('name', 'billing.ver')->count())->toBe(1);
});

// ---------------------------------------------------------------
// payment_receipt hasMany payment_allocations + payment_allocation belongsTo invoice
// (relaciones adicionales declaradas en el bloque, §4)
// ---------------------------------------------------------------
test('payment receipt hasMany allocations and allocation belongsTo invoice', function (): void {
    $org = billingTestOrg();
    $condo = billingTestCondominium($org);
    $property = billingTestProperty($condo);
    $actor = billingTestUser($org);
    $period = billingTestPeriod($condo);
    $run = billingTestRun($period, $actor);

    $invoice = EloquentInvoice::create([
        'condominium_id' => $condo->id,
        'property_id' => $property->id,
        'billing_period_id' => $period->id,
        'billing_run_id' => $run->id,
        'numero' => 'FAC-0003',
        'fecha_emision' => '2026-07-01',
        'fecha_vencimiento' => '2026-07-15',
        'valor_total' => 30000,
        'saldo' => 30000,
    ]);

    $contact = new EloquentContact([
        'organization_id' => $org->id,
        'nombre' => 'Pagador Dos',
        'email' => 'pagador2@urbania.test',
    ]);
    $contact->save();

    $receipt = EloquentPaymentReceipt::create([
        'condominium_id' => $condo->id,
        'property_id' => $property->id,
        'contact_id' => $contact->id,
        'valor' => 30000,
        'fecha' => '2026-07-05',
        'medio' => 'efectivo',
        'created_by' => $actor->id,
    ]);

    $allocation = $receipt->allocations()->create([
        'invoice_id' => $invoice->id,
        'valor_aplicado' => 30000,
    ]);

    expect($receipt->allocations()->count())->toBe(1);
    expect($allocation->invoice->id)->toBe($invoice->id);
});
