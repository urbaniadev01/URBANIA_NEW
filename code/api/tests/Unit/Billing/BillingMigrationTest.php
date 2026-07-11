<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\artisan;

// Migrations are managed manually here (no RefreshDatabase) so we can test the
// migrate → rollback → migrate cycle explicitly, same pattern as PropertiesMigrationTest
// and DirectorioMigrationTest.

// Los 8 archivos de migración que agregó COBRANZA-B01. Se apuntan explícitamente con
// --path en vez de un `--step=8` relativo: el directorio plano `database/migrations/`
// hace que cualquier migración posterior (la de `failed_jobs` de COBRANZA-B03, sin ir
// más lejos) cambie qué significa "las últimas 8", rompiendo un rollback por pasos.
// Mismo fix que ya se aplicó a DirectorioMigrationTest por la misma causa.
const COBRANZA_B01_MIGRATION_PATHS = [
    'database/migrations/2026_07_11_000024_create_charge_concepts_table.php',
    'database/migrations/2026_07_11_000025_create_billing_periods_table.php',
    'database/migrations/2026_07_11_000026_create_billing_runs_table.php',
    'database/migrations/2026_07_11_000027_create_invoices_table.php',
    'database/migrations/2026_07_11_000028_create_invoice_items_table.php',
    'database/migrations/2026_07_11_000029_create_payment_receipts_table.php',
    'database/migrations/2026_07_11_000030_create_payment_allocations_table.php',
    'database/migrations/2026_07_11_000031_create_peace_certificates_table.php',
];

beforeEach(function (): void {
    // Isolated per-process database — see useIsolatedMigrationTestDatabase() in tests/Pest.php
    useIsolatedMigrationTestDatabase('billing');

    artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
});

$billingTables = [
    'charge_concepts',
    'billing_periods',
    'billing_runs',
    'invoices',
    'invoice_items',
    'payment_receipts',
    'payment_allocations',
    'peace_certificates',
];

// ---------------------------------------------------------------
// Criteria 1-3: reversibility
// ---------------------------------------------------------------
test('migrate creates all 8 billing tables', function () use ($billingTables): void {
    foreach ($billingTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Table '{$table}' should exist after migration");
    }
});

test('migrate:rollback drops all 8 billing tables', function () use ($billingTables): void {
    artisan('migrate:rollback', ['--force' => true, '--path' => COBRANZA_B01_MIGRATION_PATHS])->assertSuccessful();

    foreach ($billingTables as $table) {
        expect(Schema::hasTable($table))->toBeFalse("Table '{$table}' should not exist after rollback");
    }
});

test('re-migrating after rollback recreates all 8 billing tables', function () use ($billingTables): void {
    artisan('migrate:rollback', ['--force' => true, '--path' => COBRANZA_B01_MIGRATION_PATHS])->assertSuccessful();
    artisan('migrate', ['--force' => true])->assertSuccessful();

    foreach ($billingTables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Table '{$table}' should exist after re-migration");
    }
});

// ---------------------------------------------------------------
// Criterion 4: CobranzaPermissionsSeeder — 11 permisos, billing.ver sin duplicar
// ---------------------------------------------------------------
test('CobranzaPermissionsSeeder inserts the permission catalog without duplicating billing.ver', function (): void {
    artisan('db:seed', ['--class' => 'CobranzaPermissionsSeeder', '--force' => true])->assertSuccessful();

    expect(DB::table('permissions')->where('name', 'billing.ver')->count())->toBe(1);

    $newPermissionNames = [
        'cobranza.conceptos.ver',
        'cobranza.conceptos.gestionar',
        'cobranza.periodos.ver',
        'cobranza.facturacion.ejecutar',
        'cobranza.facturas.ver',
        'cobranza.facturas.gestionar',
        'pagos.registrar',
        'pagos.anular',
        'cobranza.paz_salvo.generar',
        'cobranza.paz_salvo.revocar',
    ];

    foreach ($newPermissionNames as $name) {
        expect(DB::table('permissions')->where('name', $name)->count())->toBe(1);
    }

    // Re-running the seeder must not duplicate anything (idempotent firstOrCreate)
    artisan('db:seed', ['--class' => 'CobranzaPermissionsSeeder', '--force' => true])->assertSuccessful();
    expect(DB::table('permissions')->where('name', 'billing.ver')->count())->toBe(1);
    foreach ($newPermissionNames as $name) {
        expect(DB::table('permissions')->where('name', $name)->count())->toBe(1);
    }
});

// ---------------------------------------------------------------
// Criterion 9: billing_runs UNIQUE(billing_period_id) WHERE estado = 'completado'
// (decisión 7, endurece R-COB-09) — constraint de BD
// ---------------------------------------------------------------
test('billing_runs has partial unique index for completado state', function (): void {
    $indexes = DB::select("
        SELECT indexname, indexdef
        FROM pg_indexes
        WHERE tablename = 'billing_runs' AND indexname = 'billing_runs_completado_unique'
    ");

    expect($indexes)->toHaveCount(1);
    expect($indexes[0]->indexdef)->toContain("estado = 'completado'");
});

// ---------------------------------------------------------------
// Criterion 10: charge_concepts.tipo CHECK constraint (set cerrado)
// ---------------------------------------------------------------
test('charge_concepts has CHECK constraint on tipo column', function (): void {
    $constraints = DB::select("
        SELECT conname FROM pg_constraint
        WHERE conrelid = 'charge_concepts'::regclass
          AND contype = 'c'
          AND conname = 'charge_concepts_tipo_check'
    ");

    expect($constraints)->toHaveCount(1);
});

test('charge_concepts has CHECK constraint on metodo_calculo column', function (): void {
    $constraints = DB::select("
        SELECT conname FROM pg_constraint
        WHERE conrelid = 'charge_concepts'::regclass
          AND contype = 'c'
          AND conname = 'charge_concepts_metodo_calculo_check'
    ");

    expect($constraints)->toHaveCount(1);
});

// ---------------------------------------------------------------
// Criterion 11: payment_receipts.medio CHECK constraint (R-COB-15)
// ---------------------------------------------------------------
test('payment_receipts has CHECK constraint on medio column', function (): void {
    $constraints = DB::select("
        SELECT conname FROM pg_constraint
        WHERE conrelid = 'payment_receipts'::regclass
          AND contype = 'c'
          AND conname = 'payment_receipts_medio_check'
    ");

    expect($constraints)->toHaveCount(1);
});
