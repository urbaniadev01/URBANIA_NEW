<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyType;

class PropertyTypeSeeder extends Seeder
{
    /**
     * Seed the system property types (organization_id = NULL).
     */
    public function run(): void
    {
        $types = [
            ['nombre' => 'Apartamento', 'descripcion' => 'Unidad de vivienda en edificio'],
            ['nombre' => 'Casa', 'descripcion' => 'Vivienda unifamiliar independiente'],
            ['nombre' => 'Local comercial', 'descripcion' => 'Unidad para uso comercial'],
            ['nombre' => 'Parqueadero', 'descripcion' => 'Espacio de estacionamiento'],
            ['nombre' => 'Depósito', 'descripcion' => 'Unidad para almacenamiento'],
        ];

        foreach ($types as $type) {
            EloquentPropertyType::create([
                'id' => Uuid::uuid7()->toString(),
                'organization_id' => null,
                'nombre' => $type['nombre'],
                'descripcion' => $type['descripcion'],
            ]);
        }
    }
}
