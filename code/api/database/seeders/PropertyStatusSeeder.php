<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;
use Urbania\Properties\Infrastructure\Models\EloquentPropertyStatus;

class PropertyStatusSeeder extends Seeder
{
    /**
     * Seed the system property statuses (organization_id = NULL).
     */
    public function run(): void
    {
        $statuses = [
            ['nombre' => 'Disponible', 'descripcion' => 'Unidad libre, sin ocupante'],
            ['nombre' => 'Ocupado', 'descripcion' => 'Unidad con ocupante activo'],
            ['nombre' => 'En mantenimiento', 'descripcion' => 'Unidad en reparación o adecuación'],
            ['nombre' => 'En remodelación', 'descripcion' => 'Unidad en proceso de remodelación'],
            ['nombre' => 'Inactivo', 'descripcion' => 'Unidad fuera de servicio'],
        ];

        foreach ($statuses as $status) {
            EloquentPropertyStatus::create([
                'id' => Uuid::uuid7()->toString(),
                'organization_id' => null,
                'nombre' => $status['nombre'],
                'descripcion' => $status['descripcion'],
            ]);
        }
    }
}
