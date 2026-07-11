<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correcciones de integridad y rendimiento sobre las tablas de COBRANZA-B01,
 * derivadas del `verify-council` de COBRANZA-B03 (bloque `verificacion_critica: true`).
 *
 * 1. `invoices_period_property_unique` — LA invariante de negocio que faltaba: **una
 *    unidad tiene a lo sumo una factura por periodo**. El council encontró tres rutas
 *    distintas de doble facturación (dos corridas concurrentes; un fallo tras el commit
 *    que deja el run `fallido` con facturas ya escritas; y la redelivery del job por un
 *    worker muerto, que re-prorratea entero). Las tres se cierran acá: el segundo
 *    escritor aborta en su primer INSERT, dentro de su propia transacción, y revierte
 *    todo. El `UNIQUE` parcial de `billing_runs` protegía el *proceso*; este protege el
 *    *dato*, y por eso también cubre a cualquier escritor futuro de `invoices`
 *    (COBRANZA-B04 con ítems manuales, un backfill, un script de soporte).
 *
 * 2. Índices sobre `billing_period_id`. `PANORAMA.md` §4 declaraba solo el compuesto
 *    `(condominium_id, billing_period_id)`, pero las queries reales (el summary de
 *    cartera que consume DASHBOARD, el conteo de facturas pendientes al cerrar un
 *    periodo) filtran **solo** por `billing_period_id` — y Postgres no puede usar un
 *    índice compuesto sin su columna líder, así que hacían Seq Scan sobre la tabla de
 *    mayor crecimiento del sistema. El diseño era fiel al panorama; el panorama no
 *    coincidía con las queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE UNIQUE INDEX invoices_period_property_unique ON invoices (billing_period_id, property_id) WHERE deleted_at IS NULL');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->index('billing_period_id', 'invoices_billing_period_id_index');
        });

        Schema::table('billing_runs', function (Blueprint $table): void {
            $table->index('billing_period_id', 'billing_runs_billing_period_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('billing_runs', function (Blueprint $table): void {
            $table->dropIndex('billing_runs_billing_period_id_index');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_billing_period_id_index');
        });

        DB::statement('DROP INDEX IF EXISTS invoices_period_property_unique');
    }
};
