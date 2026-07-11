<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\OccupantTypeSeeder;
use Database\Seeders\PropertyStatusSeeder;
use Database\Seeders\PropertyTypeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Urbania\Auth\Infrastructure\Models\EloquentContact;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Directorio\Infrastructure\Models\EloquentOccupantType;
use Urbania\Directorio\Infrastructure\Models\EloquentPropertyOccupant;
use Urbania\Properties\Infrastructure\Models\EloquentCondominium;
use Urbania\Properties\Infrastructure\Models\EloquentProperty;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyStatus;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyType;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

function directorioTestOrg(): EloquentOrganization
{
    $org = new EloquentOrganization(['nombre' => 'Directorio Test Org']);
    $org->save();

    return $org;
}

function directorioTestCondominium(EloquentOrganization $org): EloquentCondominium
{
    $condo = new EloquentCondominium(['organization_id' => $org->id, 'nombre' => 'Condominio DIR']);
    $condo->save();

    return $condo;
}

function directorioTestProperty(EloquentCondominium $condo, string $codigo = 'D-101'): EloquentProperty
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

function directorioTestOccupantType(): EloquentOccupantType
{
    if (EloquentOccupantType::query()->count() === 0) {
        seed(OccupantTypeSeeder::class);
    }

    return EloquentOccupantType::query()->whereNull('organization_id')->first();
}

// ---------------------------------------------------------------
// Criterion 4: contact can be created without user_id
// ---------------------------------------------------------------
test('a contact can be created without a user_id', function (): void {
    $org = directorioTestOrg();

    $contact = new EloquentContact([
        'organization_id' => $org->id,
        'user_id' => null,
        'nombre' => 'Propietario Ausente',
        'email' => 'ausente@urbania.test',
    ]);
    $contact->save();

    expect($contact->user_id)->toBeNull();
    $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'user_id' => null]);
});

// ---------------------------------------------------------------
// Criterion 5: two contacts without user_id in the same org don't collide
// ---------------------------------------------------------------
test('two contacts without user_id in the same organization do not collide', function (): void {
    $org = directorioTestOrg();

    $a = new EloquentContact(['organization_id' => $org->id, 'nombre' => 'A', 'email' => 'a@urbania.test']);
    $a->save();

    $b = new EloquentContact(['organization_id' => $org->id, 'nombre' => 'B', 'email' => 'b@urbania.test']);
    $b->save();

    expect($a->id)->not->toBe($b->id);
    expect(EloquentContact::query()->whereNull('user_id')->count())->toBe(2);
});

// ---------------------------------------------------------------
// Criterion 6: duplicate user_id still fails
// ---------------------------------------------------------------
test('two contacts with the same non-null user_id violate the unique constraint', function (): void {
    $org = directorioTestOrg();
    $user = User::create([
        'organization_id' => $org->id,
        'email' => 'dup-user@urbania.test',
        'password_hash' => 'irrelevant',
        'estado' => 'active',
    ]);

    $first = new EloquentContact(['organization_id' => $org->id, 'user_id' => $user->id, 'nombre' => 'Uno', 'email' => 'uno@urbania.test']);
    $first->save();

    $second = new EloquentContact(['organization_id' => $org->id, 'user_id' => $user->id, 'nombre' => 'Dos', 'email' => 'dos@urbania.test']);

    expect(fn () => $second->save())->toThrow(QueryException::class);
});

// ---------------------------------------------------------------
// Criterion 7 (seeder — see DirectorioMigrationTest for full coverage)
// ---------------------------------------------------------------
test('OccupantType::first() has no created_by (seeded system catalog)', function (): void {
    directorioTestOccupantType();

    expect(EloquentOccupantType::query()->first()->created_by)->toBeNull();
});

// ---------------------------------------------------------------
// Criterion 8: Contact->occupations() relation
// ---------------------------------------------------------------
test('contact occupations relation creates a property_occupant', function (): void {
    $org = directorioTestOrg();
    $condo = directorioTestCondominium($org);
    $property = directorioTestProperty($condo);
    $occupantType = directorioTestOccupantType();

    $contact = new EloquentContact(['organization_id' => $org->id, 'nombre' => 'Ocupante', 'email' => 'oc@urbania.test']);
    $contact->save();

    $occupation = $contact->occupations()->create([
        'property_id' => $property->id,
        'occupant_type_id' => $occupantType->id,
    ]);

    expect($occupation->contact_id)->toBe($contact->id);
    expect($contact->occupations()->count())->toBe(1);
});

// ---------------------------------------------------------------
// Criterion 9: Property->occupants() relation (inverse, added to Properties' model)
// ---------------------------------------------------------------
test('property occupants relation creates a property_occupant', function (): void {
    $org = directorioTestOrg();
    $condo = directorioTestCondominium($org);
    $property = directorioTestProperty($condo);
    $occupantType = directorioTestOccupantType();

    $contact = new EloquentContact(['organization_id' => $org->id, 'nombre' => 'Ocupante2', 'email' => 'oc2@urbania.test']);
    $contact->save();

    $occupation = $property->occupants()->create([
        'contact_id' => $contact->id,
        'occupant_type_id' => $occupantType->id,
    ]);

    expect($occupation->property_id)->toBe($property->id);
    expect($property->occupants()->count())->toBe(1);
});

// ---------------------------------------------------------------
// Criterion 10: R-DIR-11 — duplicate (contact, property, occupant_type) fails
// ---------------------------------------------------------------
test('duplicate contact+property+occupant_type violates R-DIR-11 unique constraint', function (): void {
    $org = directorioTestOrg();
    $condo = directorioTestCondominium($org);
    $property = directorioTestProperty($condo);
    $occupantType = directorioTestOccupantType();

    $contact = new EloquentContact(['organization_id' => $org->id, 'nombre' => 'Dup', 'email' => 'dupocc@urbania.test']);
    $contact->save();

    EloquentPropertyOccupant::create([
        'contact_id' => $contact->id,
        'property_id' => $property->id,
        'occupant_type_id' => $occupantType->id,
    ]);

    expect(fn () => EloquentPropertyOccupant::create([
        'contact_id' => $contact->id,
        'property_id' => $property->id,
        'occupant_type_id' => $occupantType->id,
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------
// Criterion 11: same contact+property, different occupant_type is allowed
// ---------------------------------------------------------------
test('same contact and property with a different occupant_type is allowed', function (): void {
    $org = directorioTestOrg();
    $condo = directorioTestCondominium($org);
    $property = directorioTestProperty($condo);
    seed(OccupantTypeSeeder::class);
    $types = EloquentOccupantType::query()->whereNull('organization_id')->take(2)->get();

    $contact = new EloquentContact(['organization_id' => $org->id, 'nombre' => 'MultiTipo', 'email' => 'multi@urbania.test']);
    $contact->save();

    EloquentPropertyOccupant::create([
        'contact_id' => $contact->id,
        'property_id' => $property->id,
        'occupant_type_id' => $types[0]->id,
    ]);

    $second = EloquentPropertyOccupant::create([
        'contact_id' => $contact->id,
        'property_id' => $property->id,
        'occupant_type_id' => $types[1]->id,
    ]);

    expect($second->exists)->toBeTrue();
    expect(EloquentPropertyOccupant::query()->where('contact_id', $contact->id)->count())->toBe(2);
});

// ---------------------------------------------------------------
// Criterion 12: R-DIR-07 — two `es_principal = true` for same property+type fails
// ---------------------------------------------------------------
test('two principal occupants for the same property and occupant_type violate R-DIR-07', function (): void {
    $org = directorioTestOrg();
    $condo = directorioTestCondominium($org);
    $property = directorioTestProperty($condo);
    $occupantType = directorioTestOccupantType();

    $contactA = new EloquentContact(['organization_id' => $org->id, 'nombre' => 'PrincipalA', 'email' => 'pa@urbania.test']);
    $contactA->save();
    $contactB = new EloquentContact(['organization_id' => $org->id, 'nombre' => 'PrincipalB', 'email' => 'pb@urbania.test']);
    $contactB->save();

    EloquentPropertyOccupant::create([
        'contact_id' => $contactA->id,
        'property_id' => $property->id,
        'occupant_type_id' => $occupantType->id,
        'es_principal' => true,
    ]);

    expect(fn () => EloquentPropertyOccupant::create([
        'contact_id' => $contactB->id,
        'property_id' => $property->id,
        'occupant_type_id' => $occupantType->id,
        'es_principal' => true,
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------
// Criterion 13: FK integrity — nonexistent references fail
// ---------------------------------------------------------------
test('property_occupants with a nonexistent reference fails on FK constraint', function (): void {
    $org = directorioTestOrg();
    $condo = directorioTestCondominium($org);
    $property = directorioTestProperty($condo);

    $contact = new EloquentContact(['organization_id' => $org->id, 'nombre' => 'FkTest', 'email' => 'fk@urbania.test']);
    $contact->save();

    expect(fn () => EloquentPropertyOccupant::create([
        'contact_id' => $contact->id,
        'property_id' => $property->id,
        'occupant_type_id' => (string) Str::orderedUuid(),
    ]))->toThrow(QueryException::class);
});

// ---------------------------------------------------------------
// Criterion 14: soft delete allows recreating the same combination
// ---------------------------------------------------------------
test('soft-deleting a property_occupant allows recreating the same combination', function (): void {
    $org = directorioTestOrg();
    $condo = directorioTestCondominium($org);
    $property = directorioTestProperty($condo);
    $occupantType = directorioTestOccupantType();

    $contact = new EloquentContact(['organization_id' => $org->id, 'nombre' => 'SoftDel', 'email' => 'softdel@urbania.test']);
    $contact->save();

    $occupation = EloquentPropertyOccupant::create([
        'contact_id' => $contact->id,
        'property_id' => $property->id,
        'occupant_type_id' => $occupantType->id,
    ]);

    $occupation->delete();

    expect($occupation->fresh()->deleted_at)->not->toBeNull();
    expect(EloquentPropertyOccupant::query()->where('contact_id', $contact->id)->count())->toBe(0);

    // R-DIR-11's partial unique index (WHERE deleted_at IS NULL) allows recreating it
    $recreated = EloquentPropertyOccupant::create([
        'contact_id' => $contact->id,
        'property_id' => $property->id,
        'occupant_type_id' => $occupantType->id,
    ]);

    expect($recreated->exists)->toBeTrue();
});
