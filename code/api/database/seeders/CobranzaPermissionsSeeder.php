<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Urbania\Authorization\Infrastructure\Models\EloquentPermission;
use Urbania\Authorization\Infrastructure\Models\EloquentRole;

class CobranzaPermissionsSeeder extends Seeder
{
    /**
     * Seed the 11 new permissions of the Billing (COBRANZA) permission catalog
     * (PANORAMA.md §5). Idempotent — safe to re-run.
     *
     * `billing.ver` is intentionally NOT part of the 11: per PANORAMA.md §5 it was
     * supposed to already exist, created by DASHBOARD as the "entry" permission that
     * gates the Cobranza nav/widgets. In practice DASHBOARD never had an API block
     * (DASHBOARD-B01/B02/B03 are all `proyecto: web`) — the permission row was never
     * persisted server-side, only referenced client-side (see
     * `code/web/src/features/dashboard/widgets/QuickLinksWidget.tsx`). This seeder
     * closes that real gap idempotently (`firstOrCreate`) so `billing.ver` exists for
     * real without creating a duplicate if some future process seeds it too — see
     * `_state/RUNBOOK.md` for the finding.
     */
    public function run(): void
    {
        EloquentPermission::firstOrCreate(
            ['name' => 'billing.ver'],
            [
                'id' => (string) Str::orderedUuid(),
                'description' => 'Ver que el módulo de Cobranza existe (nav, widgets)',
            ],
        );

        $permissions = [
            ['name' => 'cobranza.conceptos.ver', 'description' => 'Ver conceptos de cobro'],
            ['name' => 'cobranza.conceptos.gestionar', 'description' => 'Crear, editar y desactivar conceptos de cobro'],
            ['name' => 'cobranza.periodos.ver', 'description' => 'Ver periodos de facturación y su resumen de cartera'],
            ['name' => 'cobranza.facturacion.ejecutar', 'description' => 'Abrir periodo y correr una corrida de facturación'],
            ['name' => 'cobranza.facturas.ver', 'description' => 'Ver cuentas de cobro y pagos'],
            ['name' => 'cobranza.facturas.gestionar', 'description' => 'Agregar y corregir ítems manuales de una cuenta de cobro'],
            ['name' => 'pagos.registrar', 'description' => 'Registrar pagos/abonos manuales'],
            ['name' => 'pagos.anular', 'description' => 'Anular un pago registrado'],
            ['name' => 'cobranza.paz_salvo.generar', 'description' => 'Generar certificado de paz y salvo'],
            ['name' => 'cobranza.paz_salvo.revocar', 'description' => 'Revocar un certificado de paz y salvo emitido'],
        ];

        foreach ($permissions as $permission) {
            EloquentPermission::firstOrCreate(
                ['name' => $permission['name']],
                [
                    'id' => (string) Str::orderedUuid(),
                    'description' => $permission['description'],
                ],
            );
        }

        // Asignación a roles de sistema — mismo criterio que admin.access en
        // RbacDemoSeeder, donde `admin` y `manager` (Administrador de conjunto) reciben
        // los mismos permisos operativos. Corre después de RbacDemoSeeder (ver
        // DatabaseSeeder), así que los roles ya existen. Idempotente vía
        // syncWithoutDetaching (mismo patrón que tests/Feature/Authorization/RbacTest.php).
        //
        // COBRANZA-B02: cobranza.conceptos.ver/gestionar.
        // COBRANZA-B03: billing.ver (sin esto, el summary de cartera que DASHBOARD
        // consumirá sería inaccesible para todo usuario — el permiso existía como fila
        // pero ningún rol lo tenía), cobranza.periodos.ver, cobranza.facturacion.ejecutar.
        //
        // `pagos.registrar`/`pagos.anular` y `cobranza.paz_salvo.*` NO se asignan acá:
        // R-COB-13 exige que no coincidan por defecto en el mismo rol (segregación de
        // funciones). Los asignará el bloque que primero los consuma (B05/B06), con la
        // separación que corresponda.
        $aAsignar = [
            'billing.ver',
            'cobranza.conceptos.ver',
            'cobranza.conceptos.gestionar',
            'cobranza.periodos.ver',
            'cobranza.facturacion.ejecutar',
        ];

        $permissionIds = EloquentPermission::whereIn('name', $aAsignar)->pluck('id')->all();

        foreach (['admin', 'manager'] as $roleName) {
            $role = EloquentRole::where('name', $roleName)->first();

            if ($role === null || $permissionIds === []) {
                continue;
            }

            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }
}
