<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Urbania\Authorization\Infrastructure\Models\EloquentPermission;
use Urbania\Authorization\Infrastructure\Models\EloquentRole;

class RbacDemoSeeder extends Seeder
{
    /**
     * Seed roles and permissions for demo/testing.
     *
     * This creates the minimum catalog needed to validate the RBAC mechanism.
     * Users and role_assignments are created by individual tests — this seeder
     * only populates the role/permission catalogs.
     */
    public function run(): void
    {
        // -----------------------------------------------------------
        // Permissions
        // -----------------------------------------------------------
        $adminAccess = EloquentPermission::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'admin.access',
            'description' => 'Acceso al panel de administración',
        ]);

        $profileView = EloquentPermission::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'profile.view',
            'description' => 'Ver perfil propio',
        ]);

        // -----------------------------------------------------------
        // Roles
        // -----------------------------------------------------------
        $adminRole = EloquentRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'admin',
            'description' => 'Administrador del sistema',
        ]);

        $residentRole = EloquentRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'resident',
            'description' => 'Residente de una unidad',
        ]);

        $managerRole = EloquentRole::create([
            'id' => (string) Str::orderedUuid(),
            'name' => 'manager',
            'description' => 'Administrador de conjunto',
        ]);

        // -----------------------------------------------------------
        // Role ↔ Permission assignments
        // -----------------------------------------------------------
        $adminRole->permissions()->attach($adminAccess->id);
        $managerRole->permissions()->attach($adminAccess->id);
        $residentRole->permissions()->attach($profileView->id);
    }
}
