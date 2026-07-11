<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Pest\Laravel\artisan;

// Migrations are managed manually here (no RefreshDatabase)
// so we can test migrate → rollback → migrate cycle explicitly,
// including the corrective migration on `contacts` (a table already SHIPPED by AUTH-B01).

// The 3 migration files this block (DIRECTORIO-B01) added. Targeted explicitly via
// --path instead of a relative `--step=3` — a flat `database/migrations/` directory
// means any later feature (e.g. COBRANZA-B01) that adds its own migrations shifts what
// "the last 3 migrations" means after a full `migrate:fresh`, silently breaking a
// step-based rollback. --path pins this test to its own 3 files regardless of how many
// migrations exist after them (found while implementing COBRANZA-B01).
const DIRECTORIO_B01_MIGRATION_PATHS = [
    'database/migrations/2026_07_10_000021_fix_contacts_organization_id_and_user_id_nullable.php',
    'database/migrations/2026_07_10_000022_create_occupant_types_table.php',
    'database/migrations/2026_07_10_000023_create_property_occupants_table.php',
];

beforeEach(function (): void {
    // Isolated per-process database — see useIsolatedMigrationTestDatabase() in tests/Pest.php
    useIsolatedMigrationTestDatabase('directorio');

    artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
});

// ---------------------------------------------------------------
// Criterion 1: backfill leaves 0 contacts with organization_id NULL
// ---------------------------------------------------------------
test('contacts backfill leaves zero rows with null organization_id', function (): void {
    // Roll back to just before the 3 DIRECTORIO-B01 migrations, so contacts
    // still has its original AUTH-B01 shape (no organization_id column).
    artisan('migrate:rollback', ['--force' => true, '--path' => DIRECTORIO_B01_MIGRATION_PATHS])->assertSuccessful();

    $orgId = (string) Str::orderedUuid();
    DB::table('organizations')->insert([
        'id' => $orgId,
        'nombre' => 'Org Backfill Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $userId = (string) Str::orderedUuid();
    DB::table('users')->insert([
        'id' => $userId,
        'organization_id' => $orgId,
        'email' => 'backfill-test@urbania.test',
        'password_hash' => 'irrelevant',
        'estado' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('contacts')->insert([
        'id' => (string) Str::orderedUuid(),
        'user_id' => $userId,
        'nombre' => 'Contacto Preexistente',
        'email' => 'backfill-test@urbania.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Re-run the 3 pending migrations, including the backfill
    artisan('migrate', ['--force' => true])->assertSuccessful();

    expect(DB::table('contacts')->whereNull('organization_id')->count())->toBe(0);
    expect(DB::table('contacts')->where('user_id', $userId)->value('organization_id'))->toBe($orgId);
});

// ---------------------------------------------------------------
// Criteria 2-3: migrate:rollback / re-migrate reversibility for the 3 new
// migrations of this block (contacts fix, occupant_types, property_occupants)
// ---------------------------------------------------------------
test('rollback of the 3 DIRECTORIO-B01 migrations removes their structures', function (): void {
    // Roll back the 3 migrations added by this block (property_occupants, occupant_types, contacts fix)
    artisan('migrate:rollback', ['--force' => true, '--path' => DIRECTORIO_B01_MIGRATION_PATHS])->assertSuccessful();

    expect(Schema::hasTable('property_occupants'))->toBeFalse();
    expect(Schema::hasTable('occupant_types'))->toBeFalse();
    expect(Schema::hasColumn('contacts', 'organization_id'))->toBeFalse();
    expect(Schema::hasColumn('contacts', 'created_by'))->toBeFalse();
});

test('re-migrating after rollback restores the 3 DIRECTORIO-B01 migrations', function (): void {
    artisan('migrate:rollback', ['--force' => true, '--path' => DIRECTORIO_B01_MIGRATION_PATHS])->assertSuccessful();
    artisan('migrate', ['--force' => true])->assertSuccessful();

    expect(Schema::hasTable('property_occupants'))->toBeTrue();
    expect(Schema::hasTable('occupant_types'))->toBeTrue();
    expect(Schema::hasColumn('contacts', 'organization_id'))->toBeTrue();
    expect(Schema::hasColumn('contacts', 'created_by'))->toBeTrue();
    expect(Schema::hasColumn('contacts', 'updated_by'))->toBeTrue();
});

// ---------------------------------------------------------------
// contacts.user_id is now nullable
// ---------------------------------------------------------------
test('contacts user_id column is nullable after the fix migration', function (): void {
    $column = DB::selectOne("
        SELECT is_nullable
        FROM information_schema.columns
        WHERE table_name = 'contacts' AND column_name = 'user_id'
    ");

    expect($column->is_nullable)->toBe('YES');
});

// ---------------------------------------------------------------
// contacts.organization_id is NOT NULL
// ---------------------------------------------------------------
test('contacts organization_id column is not null after the fix migration', function (): void {
    $column = DB::selectOne("
        SELECT is_nullable
        FROM information_schema.columns
        WHERE table_name = 'contacts' AND column_name = 'organization_id'
    ");

    expect($column->is_nullable)->toBe('NO');
});

// ---------------------------------------------------------------
// Partial unique indexes exist (R-DIR-07, R-DIR-11, and the contacts.user_id fix)
// ---------------------------------------------------------------
test('contacts has a partial unique index on user_id', function (): void {
    $indexes = DB::select("
        SELECT indexname, indexdef
        FROM pg_indexes
        WHERE tablename = 'contacts' AND indexname = 'contacts_user_id_unique'
    ");

    expect($indexes)->toHaveCount(1);
    expect($indexes[0]->indexdef)->toContain('WHERE (user_id IS NOT NULL)');
});

test('property_occupants has partial unique index for R-DIR-11', function (): void {
    $indexes = DB::select("
        SELECT indexname, indexdef
        FROM pg_indexes
        WHERE tablename = 'property_occupants'
          AND indexname = 'property_occupants_contact_property_type_unique'
    ");

    expect($indexes)->toHaveCount(1);
    expect($indexes[0]->indexdef)->toContain('WHERE (deleted_at IS NULL)');
});

test('property_occupants has partial unique index for R-DIR-07 (principal)', function (): void {
    $indexes = DB::select("
        SELECT indexname, indexdef
        FROM pg_indexes
        WHERE tablename = 'property_occupants'
          AND indexname = 'property_occupants_principal_unique'
    ");

    expect($indexes)->toHaveCount(1);
    expect($indexes[0]->indexdef)->toContain('es_principal');
    expect($indexes[0]->indexdef)->toContain('WHERE');
});

// ---------------------------------------------------------------
// property_occupants has plain indexes on property_id and occupant_type_id
// (added after verify-council flagged the missing leading-column indexes)
// ---------------------------------------------------------------
test('property_occupants has plain indexes on property_id and occupant_type_id', function (): void {
    $indexes = DB::select("
        SELECT indexname, indexdef
        FROM pg_indexes
        WHERE tablename = 'property_occupants'
    ");

    $indexDefs = collect($indexes)->pluck('indexdef')->implode(' | ');

    expect($indexDefs)->toContain('property_occupants_property_id_index');
    expect($indexDefs)->toContain('property_occupants_occupant_type_id_index');
});

// ---------------------------------------------------------------
// down() refuses to roll back if a contact has user_id IS NULL (R-DIR-02 data
// cannot coexist with the pre-B01 NOT NULL constraint) — added after
// verify-council flagged this as an untested reversibility gap.
// ---------------------------------------------------------------
test('rollback fails loudly when a contact has user_id IS NULL', function (): void {
    $orgId = (string) Str::orderedUuid();
    DB::table('organizations')->insert([
        'id' => $orgId,
        'nombre' => 'Org Rollback Guard Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('contacts')->insert([
        'id' => (string) Str::orderedUuid(),
        'organization_id' => $orgId,
        'user_id' => null,
        'nombre' => 'Propietario Ausente',
        'email' => 'ausente-rollback-test@urbania.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => artisan('migrate:rollback', ['--force' => true, '--path' => DIRECTORIO_B01_MIGRATION_PATHS])->run())
        ->toThrow(RuntimeException::class, 'Cannot roll back: 1 contact(s) have user_id IS NULL');

    // The migration itself is still applied — the guard threw before altering anything.
    expect(Schema::hasColumn('contacts', 'organization_id'))->toBeTrue();
});

// ---------------------------------------------------------------
// Criterion 7: OccupantTypeSeeder creates the 4 base types
// ---------------------------------------------------------------
test('OccupantTypeSeeder creates the 4 base occupant types', function (): void {
    artisan('db:seed', ['--class' => 'OccupantTypeSeeder', '--force' => true])->assertSuccessful();

    $names = DB::table('occupant_types')->pluck('nombre')->all();

    expect($names)->toHaveCount(4)
        ->and($names)->toContain('Propietario', 'Residente', 'Arrendatario', 'Familiar');

    expect(DB::table('occupant_types')->whereNull('organization_id')->count())->toBe(4);
});
