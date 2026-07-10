<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\artisan;

// Migrations are managed manually here (no RefreshDatabase)
// so we can test migrate → rollback → migrate cycle explicitly.

beforeEach(function (): void {
    // Start each test with a clean, fully-migrated database
    artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
});

// ---------------------------------------------------------------
// Criterion 1: migrate creates all 6 tables
// ---------------------------------------------------------------

test('migrate creates all 6 properties tables', function (): void {
    $expectedTables = [
        'condominiums',
        'towers',
        'property_types',
        'property_statuses',
        'properties',
        'property_coefficients',
    ];

    foreach ($expectedTables as $table) {
        expect(Schema::hasTable($table))
            ->toBeTrue("Table '{$table}' should exist after migration");
    }
});

// ---------------------------------------------------------------
// Criterion 2: migrate:rollback drops the 6 tables
// ---------------------------------------------------------------

test('migrate:rollback drops all properties tables', function (): void {
    artisan('migrate:rollback', ['--force' => true])->assertSuccessful();

    $expectedTables = [
        'condominiums',
        'towers',
        'property_types',
        'property_statuses',
        'properties',
        'property_coefficients',
    ];

    foreach ($expectedTables as $table) {
        expect(Schema::hasTable($table))
            ->toBeFalse("Table '{$table}' should NOT exist after rollback");
    }
});

// ---------------------------------------------------------------
// Criterion 3: rollback + re-migrate (down() reversible proof)
// ---------------------------------------------------------------

test('migrate after rollback recreates all 6 tables', function (): void {
    // Rollback
    artisan('migrate:rollback', ['--force' => true])->assertSuccessful();

    // Re-migrate
    artisan('migrate', ['--force' => true])->assertSuccessful();

    $expectedTables = [
        'condominiums',
        'towers',
        'property_types',
        'property_statuses',
        'properties',
        'property_coefficients',
    ];

    foreach ($expectedTables as $table) {
        expect(Schema::hasTable($table))
            ->toBeTrue("Table '{$table}' should exist after re-migration");
    }
});

// ---------------------------------------------------------------
// Criterion: created_by / updated_by columns exist on all 6 tables
// ---------------------------------------------------------------

test('all 6 property tables have created_by and updated_by columns', function (string $table): void {
    expect(Schema::hasColumn($table, 'created_by'))
        ->toBeTrue("Table '{$table}' should have created_by column");

    expect(Schema::hasColumn($table, 'updated_by'))
        ->toBeTrue("Table '{$table}' should have updated_by column");
})->with([
    'condominiums',
    'towers',
    'property_types',
    'property_statuses',
    'properties',
    'property_coefficients',
]);

// ---------------------------------------------------------------
// Criterion: property_coefficients has CHECK constraint on tipo
// ---------------------------------------------------------------

test('property_coefficients has CHECK constraint on tipo column', function (): void {
    // Query PostgreSQL to verify the CHECK constraint exists
    $constraints = DB::select("
        SELECT conname
        FROM pg_constraint
        WHERE conrelid = 'property_coefficients'::regclass
          AND contype = 'c'
          AND conname = 'property_coefficients_tipo_check'
    ");

    expect($constraints)->toHaveCount(1)
        ->and($constraints[0]->conname)->toEqual('property_coefficients_tipo_check');
});
