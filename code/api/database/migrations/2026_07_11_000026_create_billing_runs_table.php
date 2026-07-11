<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('billing_period_id');
            $table->uuid('ejecutado_por');
            $table->timestampTz('fecha');
            $table->text('estado');
            $table->jsonb('resumen')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('billing_period_id')
                ->references('id')
                ->on('billing_periods')
                ->onDelete('cascade');

            $table->foreign('ejecutado_por')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        // R-COB-22: en_proceso (202 + polling) / completado / fallido
        DB::statement("ALTER TABLE billing_runs ADD CONSTRAINT billing_runs_estado_check CHECK (estado IN ('en_proceso', 'completado', 'fallido'))");

        // R-COB-09 (decisión 7 de PANORAMA §4): un solo billing_run completado por periodo,
        // reforzado con constraint de BD, no solo verificación de aplicación.
        DB::statement("CREATE UNIQUE INDEX billing_runs_completado_unique ON billing_runs (billing_period_id) WHERE estado = 'completado' AND deleted_at IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_runs');
    }
};
