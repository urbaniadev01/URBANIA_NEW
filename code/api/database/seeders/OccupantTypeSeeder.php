<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;
use Urbania\Directorio\Infrastructure\Models\EloquentOccupantType;

class OccupantTypeSeeder extends Seeder
{
    /**
     * Seed the system occupant types (organization_id = NULL).
     */
    public function run(): void
    {
        $types = [
            ['nombre' => 'Propietario', 'descripcion' => 'Dueño registrado de la unidad'],
            ['nombre' => 'Residente', 'descripcion' => 'Habita la unidad de forma permanente'],
            ['nombre' => 'Arrendatario', 'descripcion' => 'Ocupa la unidad bajo contrato de arriendo'],
            ['nombre' => 'Familiar', 'descripcion' => 'Familiar de un ocupante principal'],
        ];

        foreach ($types as $type) {
            EloquentOccupantType::create([
                'id' => Uuid::uuid7()->toString(),
                'organization_id' => null,
                'nombre' => $type['nombre'],
                'descripcion' => $type['descripcion'],
            ]);
        }
    }
}
