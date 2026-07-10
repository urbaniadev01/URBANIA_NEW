<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyCoefficient;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyStatus;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyType;
use Urbania\Properties\Infrastructure\Models\EloquentTower;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------
// Helpers — create dependent records
// ---------------------------------------------------------------

function createPropertiesTestOrg(): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => 'Urbania Test Properties']);
    $org->save();

    return $org;
}

function createCondominium(?EloquentOrganization $org = null): EloquentCondominium
{
    $org ??= createPropertiesTestOrg();

    $condominium = new EloquentCondominium([
        'organization_id' => $org->id,
        'nombre' => 'Condominio Test '.bin2hex(random_bytes(4)),
        'direccion' => 'Calle Test 123',
        'nit' => '900123456-7',
    ]);
    $condominium->save();

    return $condominium;
}

function createPropertyType(): EloquentPropertyType
{
    $type = new EloquentPropertyType([
        'organization_id' => null,
        'nombre' => 'Test Type '.bin2hex(random_bytes(4)),
    ]);
    $type->save();

    return $type;
}

function createPropertyStatus(): EloquentPropertyStatus
{
    $status = new EloquentPropertyStatus([
        'organization_id' => null,
        'nombre' => 'Test Status '.bin2hex(random_bytes(4)),
    ]);
    $status->save();

    return $status;
}

function createTower(?EloquentCondominium $condominium = null): EloquentTower
{
    $condominium ??= createCondominium();

    $tower = new EloquentTower([
        'condominium_id' => $condominium->id,
        'nombre' => 'Torre Test '.bin2hex(random_bytes(4)),
    ]);
    $tower->save();

    return $tower;
}

function createProperty(?EloquentCondominium $condominium = null): EloquentProperty
{
    $condominium ??= createCondominium();
    $type = createPropertyType();
    $status = createPropertyStatus();

    $property = new EloquentProperty([
        'condominium_id' => $condominium->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => 'U-'.bin2hex(random_bytes(4)),
    ]);
    $property->save();

    return $property;
}

// ---------------------------------------------------------------
// Criterion 6: condominium → towers (hasMany)
// ---------------------------------------------------------------

test('condominium hasMany towers with correct FK', function (): void {
    $condominium = createCondominium();

    $tower = $condominium->towers()->create([
        'nombre' => 'Torre Alpha',
    ]);

    expect($tower)->not->toBeNull()
        ->and($tower->condominium_id)->toEqual($condominium->id);
});

// ---------------------------------------------------------------
// Criterion 7: tower → condominium (belongsTo)
// ---------------------------------------------------------------

test('tower belongsTo condominium', function (): void {
    $condominium = createCondominium();

    $tower = new EloquentTower([
        'condominium_id' => $condominium->id,
        'nombre' => 'Torre Beta',
    ]);
    $tower->save();

    $related = $tower->condominium()->first();

    expect($related)->not->toBeNull()
        ->and($related->id)->toEqual($condominium->id);
});

// ---------------------------------------------------------------
// Criterion 8: condominium → properties (hasMany)
// ---------------------------------------------------------------

test('condominium hasMany properties', function (): void {
    $condominium = createCondominium();
    $type = createPropertyType();
    $status = createPropertyStatus();

    $property = $condominium->properties()->create([
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => '101',
    ]);

    expect($property)->not->toBeNull()
        ->and($property->condominium_id)->toEqual($condominium->id);
});

// ---------------------------------------------------------------
// Criterion 9: property → type (belongsTo)
// ---------------------------------------------------------------

test('property belongsTo property_type', function (): void {
    $type = createPropertyType();
    $condominium = createCondominium();
    $status = createPropertyStatus();

    $property = new EloquentProperty([
        'condominium_id' => $condominium->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => '101',
    ]);
    $property->save();

    $related = $property->type()->first();

    expect($related)->not->toBeNull()
        ->and($related->id)->toEqual($type->id);
});

// ---------------------------------------------------------------
// Criterion 10: property → coefficients (hasMany)
// ---------------------------------------------------------------

test('property hasMany coefficients', function (): void {
    $property = createProperty();

    $coefficient = $property->coefficients()->create([
        'tipo' => 'copropiedad',
        'valor' => 0.2500,
        'vigente_desde' => '2026-01-01',
    ]);

    expect($coefficient)->not->toBeNull()
        ->and($coefficient->property_id)->toEqual($property->id)
        ->and((float) $coefficient->valor)->toEqual(0.2500);
});

// ---------------------------------------------------------------
// Criterion 11: FK constraint — tower with non-existent condominium
// ---------------------------------------------------------------

test('tower with non-existent condominium_id throws FK error', function (): void {
    $fakeId = Uuid::uuid7()->toString();

    expect(fn () => EloquentTower::create([
        'condominium_id' => $fakeId,
        'nombre' => 'Torre Fantasma',
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------
// Criterion 12: Unique code per condominium
// ---------------------------------------------------------------

test('property with duplicate codigo+condominium_id throws uniqueness error', function (): void {
    $condominium = createCondominium();
    $type = createPropertyType();
    $status = createPropertyStatus();

    // First property
    EloquentProperty::create([
        'condominium_id' => $condominium->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => 'DUP-101',
    ]);

    // Second property with same condominium_id + codigo should fail
    expect(fn () => EloquentProperty::create([
        'condominium_id' => $condominium->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => 'DUP-101',
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------
// Criterion 13: Soft delete
// ---------------------------------------------------------------

test('condominium soft delete fills deleted_at', function (): void {
    $condominium = createCondominium();

    expect($condominium->deleted_at)->toBeNull();

    $condominium->delete();

    expect($condominium->deleted_at)->not->toBeNull();

    // Should not appear in default queries
    $found = EloquentCondominium::find($condominium->id);
    expect($found)->toBeNull();

    // But should be recoverable with trashed
    $trashed = EloquentCondominium::withTrashed()->find($condominium->id);
    expect($trashed)->not->toBeNull();
});

// ---------------------------------------------------------------
// Criterion 14: Force delete
// ---------------------------------------------------------------

test('condominium forceDelete removes record physically', function (): void {
    $condominium = createCondominium();
    $id = $condominium->id;

    $condominium->forceDelete();

    $found = EloquentCondominium::withTrashed()->find($id);
    expect($found)->toBeNull();
});

// ---------------------------------------------------------------
// Criterion 15: UUID v7 generated automatically
// ---------------------------------------------------------------

test('property id is a valid UUID v7', function (): void {
    $property = createProperty();

    $id = $property->id;

    // Must be a valid UUID string
    expect(Uuid::isValid($id))->toBeTrue();

    // Must be UUID v7 (version nibble = 7)
    $uuid = Uuid::fromString($id);
    $version = (int) substr($id, 14, 1);
    expect($version)->toEqual(7, "UUID version should be 7, got version {$version}");
});

// ---------------------------------------------------------------
// Extra: property belongsTo status and belongsTo tower
// ---------------------------------------------------------------

test('property belongsTo status', function (): void {
    $status = createPropertyStatus();
    $condominium = createCondominium();
    $type = createPropertyType();

    $property = new EloquentProperty([
        'condominium_id' => $condominium->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => '201',
    ]);
    $property->save();

    $related = $property->status()->first();

    expect($related)->not->toBeNull()
        ->and($related->id)->toEqual($status->id);
});

test('property belongsTo tower', function (): void {
    $condominium = createCondominium();
    $tower = createTower($condominium);
    $type = createPropertyType();
    $status = createPropertyStatus();

    $property = new EloquentProperty([
        'condominium_id' => $condominium->id,
        'tower_id' => $tower->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => '301',
    ]);
    $property->save();

    $related = $property->tower()->first();

    expect($related)->not->toBeNull()
        ->and($related->id)->toEqual($tower->id);
});

test('property belongsTo condominium', function (): void {
    $condominium = createCondominium();
    $property = createProperty($condominium);

    $related = $property->condominium()->first();

    expect($related)->not->toBeNull()
        ->and($related->id)->toEqual($condominium->id);
});

test('tower hasMany properties', function (): void {
    $condominium = createCondominium();
    $tower = createTower($condominium);
    $type = createPropertyType();
    $status = createPropertyStatus();

    $property = $tower->properties()->create([
        'condominium_id' => $condominium->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => 'T-101',
    ]);

    expect($property)->not->toBeNull()
        ->and($property->tower_id)->toEqual($tower->id);
});

test('coefficient belongsTo property', function (): void {
    $property = createProperty();

    $coefficient = new EloquentPropertyCoefficient([
        'property_id' => $property->id,
        'tipo' => 'copropiedad',
        'valor' => 0.5000,
        'vigente_desde' => '2026-01-01',
    ]);
    $coefficient->save();

    $related = $coefficient->property()->first();

    expect($related)->not->toBeNull()
        ->and($related->id)->toEqual($property->id);
});

// ---------------------------------------------------------------
// Seeders test: criteria 4–5
// ---------------------------------------------------------------

test('PropertyTypeSeeder inserts 5 system types with organization_id = NULL', function (): void {
    // Ensure the table exists
    artisan('migrate', ['--force' => true])->assertSuccessful();

    artisan('db:seed', [
        '--class' => 'PropertyTypeSeeder',
        '--force' => true,
    ])->assertSuccessful();

    $types = EloquentPropertyType::all();

    expect($types)->toHaveCount(5);

    foreach ($types as $type) {
        expect($type->organization_id)->toBeNull();
        expect($type->created_by)->toBeNull('created_by should be NULL for system catalog types');
        expect($type->updated_by)->toBeNull('updated_by should be NULL for system catalog types');
    }

    $names = $types->pluck('nombre')->toArray();
    expect($names)->toContain('Apartamento')
        ->toContain('Casa')
        ->toContain('Local comercial')
        ->toContain('Parqueadero')
        ->toContain('Depósito');
});

test('PropertyStatusSeeder inserts 5 system statuses with organization_id = NULL', function (): void {
    // Ensure the table exists
    artisan('migrate', ['--force' => true])->assertSuccessful();

    artisan('db:seed', [
        '--class' => 'PropertyStatusSeeder',
        '--force' => true,
    ])->assertSuccessful();

    $statuses = EloquentPropertyStatus::all();

    expect($statuses)->toHaveCount(5);

    foreach ($statuses as $status) {
        expect($status->organization_id)->toBeNull();
        expect($status->created_by)->toBeNull('created_by should be NULL for system catalog statuses');
        expect($status->updated_by)->toBeNull('updated_by should be NULL for system catalog statuses');
    }

    $names = $statuses->pluck('nombre')->toArray();
    expect($names)->toContain('Disponible')
        ->toContain('Ocupado')
        ->toContain('En mantenimiento')
        ->toContain('En remodelación')
        ->toContain('Inactivo');
});

// ---------------------------------------------------------------
// Criterion 16: CHECK constraint on property_coefficients.tipo
// ---------------------------------------------------------------

test('property coefficient rejects invalid tipo via CHECK constraint', function (): void {
    $property = createProperty();

    expect(fn () => EloquentPropertyCoefficient::create([
        'property_id' => $property->id,
        'tipo' => 'invalido',
        'valor' => 0.5000,
        'vigente_desde' => '2026-01-01',
    ]))->toThrow(QueryException::class);
});

test('property coefficient accepts all 4 valid tipos', function (string $tipo): void {
    $property = createProperty();

    $coefficient = EloquentPropertyCoefficient::create([
        'property_id' => $property->id,
        'tipo' => $tipo,
        'valor' => 0.2500,
        'vigente_desde' => '2026-01-01',
    ]);

    expect($coefficient->tipo)->toEqual($tipo);
})->with([
    'copropiedad',
    'parqueadero',
    'deposito',
    'mantenimiento',
]);

// ---------------------------------------------------------------
// Criterion 17: Partial unique index — one active coefficient per property+tipo
// ---------------------------------------------------------------

test('partial unique index enforces one active coefficient per property+tipo', function (): void {
    $property = createProperty();

    // First active coefficient
    EloquentPropertyCoefficient::create([
        'property_id' => $property->id,
        'tipo' => 'copropiedad',
        'valor' => 0.2500,
        'vigente_desde' => '2026-01-01',
    ]);

    // Second active (vigente_hasta IS NULL) with same property_id + tipo should fail
    expect(fn () => EloquentPropertyCoefficient::create([
        'property_id' => $property->id,
        'tipo' => 'copropiedad',
        'valor' => 0.3000,
        'vigente_desde' => '2026-06-01',
    ]))->toThrow(QueryException::class);
});

test('partial unique index allows closed coefficient (vigente_hasta NOT NULL)', function (): void {
    $property = createProperty();

    // First active coefficient
    EloquentPropertyCoefficient::create([
        'property_id' => $property->id,
        'tipo' => 'copropiedad',
        'valor' => 0.2500,
        'vigente_desde' => '2026-01-01',
        'vigente_hasta' => '2026-06-01',
    ]);

    // Second with same tipo but vigente_hasta IS NULL should succeed
    // because the first one is closed (vigente_hasta NOT NULL)
    $active = EloquentPropertyCoefficient::create([
        'property_id' => $property->id,
        'tipo' => 'copropiedad',
        'valor' => 0.3000,
        'vigente_desde' => '2026-07-01',
    ]);

    expect($active)->not->toBeNull()
        ->and($active->tipo)->toEqual('copropiedad');
});

// ---------------------------------------------------------------
// Criterion 18: created_by / updated_by FK columns exist
// ---------------------------------------------------------------

test('condominium has created_by and updated_by columns', function (): void {
    $condominium = createCondominium();

    // Columns exist and are nullable
    $raw = DB::table('condominiums')->where('id', $condominium->id)->first();

    expect($raw)->not->toBeNull()
        ->and($raw->created_by)->toBeNull()
        ->and($raw->updated_by)->toBeNull();
});

test('property can set created_by and updated_by', function (): void {
    $org = createPropertiesTestOrg();
    $user = new User([
        'organization_id' => $org->id,
        'email' => 'audit@test.com',
        'password_hash' => 'hash',
        'estado' => 'active',
    ]);
    $user->save();

    $condominium = createCondominium($org);
    $type = createPropertyType();
    $status = createPropertyStatus();

    $property = EloquentProperty::create([
        'condominium_id' => $condominium->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => 'AUDIT-001',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    expect($property->created_by)->toEqual($user->id)
        ->and($property->updated_by)->toEqual($user->id);
});

// ---------------------------------------------------------------
// Criterion 19: createdBy() / updatedBy() relationships
// ---------------------------------------------------------------

test('condominium createdBy returns user', function (): void {
    $org = createPropertiesTestOrg();
    $user = new User([
        'organization_id' => $org->id,
        'email' => 'creator@test.com',
        'password_hash' => 'hash',
        'estado' => 'active',
    ]);
    $user->save();

    $condominium = new EloquentCondominium([
        'organization_id' => $org->id,
        'nombre' => 'Condominio Audit '.bin2hex(random_bytes(4)),
        'created_by' => $user->id,
    ]);
    $condominium->save();

    $related = $condominium->createdBy()->first();

    expect($related)->not->toBeNull()
        ->and($related->id)->toEqual($user->id);
});

test('property updatedBy returns user', function (): void {
    $org = createPropertiesTestOrg();
    $user = new User([
        'organization_id' => $org->id,
        'email' => 'updater@test.com',
        'password_hash' => 'hash',
        'estado' => 'active',
    ]);
    $user->save();

    $condominium = createCondominium($org);
    $type = createPropertyType();
    $status = createPropertyStatus();

    $property = new EloquentProperty([
        'condominium_id' => $condominium->id,
        'property_type_id' => $type->id,
        'property_status_id' => $status->id,
        'codigo' => 'UPD-001',
        'updated_by' => $user->id,
    ]);
    $property->save();

    $related = $property->updatedBy()->first();

    expect($related)->not->toBeNull()
        ->and($related->id)->toEqual($user->id);
});

test('created_by FK rejects non-existent user', function (): void {
    $fakeUserId = Uuid::uuid7()->toString();
    $org = createPropertiesTestOrg();

    expect(fn () => EloquentCondominium::create([
        'organization_id' => $org->id,
        'nombre' => 'FK Test '.bin2hex(random_bytes(4)),
        'created_by' => $fakeUserId,
    ]))->toThrow(QueryException::class);
});
