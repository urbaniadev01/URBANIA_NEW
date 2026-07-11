<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Urbania\Auth\Infrastructure\Models\EloquentContact;
use Urbania\Auth\Infrastructure\Models\EloquentOrganization;
use Urbania\Authorization\Infrastructure\Models\EloquentRole;
use Urbania\Authorization\Infrastructure\Models\EloquentRoleAssignment;

class DemoUserSeeder extends Seeder
{
    /**
     * Seed a demo admin user for manual testing.
     *
     * Email: admin@urbania.test
     * Password: Admin123!
     *
     * This user belongs to a demo organization and has the 'admin' role
     * with organization-level scope — full access to all PROPIEDADES features.
     */
    public function run(): void
    {
        // -----------------------------------------------------------
        // Organization
        // -----------------------------------------------------------
        $org = EloquentOrganization::create([
            'id' => (string) Str::orderedUuid(),
            'nombre' => 'Demo Condominio',
        ]);

        // -----------------------------------------------------------
        // User
        // -----------------------------------------------------------
        $user = User::create([
            'id' => (string) Str::orderedUuid(),
            'organization_id' => $org->id,
            'email' => 'admin@urbania.test',
            'password_hash' => password_hash('Admin123!', PASSWORD_BCRYPT),
            'estado' => 'active',
        ]);

        // -----------------------------------------------------------
        // Contact — nombre mostrado en UI (AuthController/MeResource
        // leen name desde user.contact?->nombre, no desde la tabla users)
        // -----------------------------------------------------------
        EloquentContact::create([
            'id' => (string) Str::orderedUuid(),
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'nombre' => 'Administrador Demo',
            'email' => $user->email,
        ]);

        // -----------------------------------------------------------
        // Role assignment — admin with organization scope
        // -----------------------------------------------------------
        $adminRole = EloquentRole::where('name', 'admin')->first();

        if (! $adminRole) {
            $this->command->warn('Admin role not found — run RbacDemoSeeder first.');

            return;
        }

        EloquentRoleAssignment::create([
            'id' => (string) Str::orderedUuid(),
            'user_id' => $user->id,
            'role_id' => $adminRole->id,
            'scope_type' => 'organization',
            'scope_id' => $org->id,
            'expires_at' => null,
        ]);

        $this->command->info('Demo user seeded: admin@urbania.test / Admin123!');
        $nombre = (string) $org->nombre;
        $id = (string) $org->id;
        $this->command->info("Organization: {$nombre} ({$id})");
    }
}
