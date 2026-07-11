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
        Schema::create('billing_periods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('condominium_id');
            $table->integer('anio');
            $table->integer('mes');
            $table->text('estado')->default('abierto');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('condominium_id')
                ->references('id')
                ->on('condominiums')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        // R-COB-10: ciclo de vida abierto → facturado → cerrado
        DB::statement("ALTER TABLE billing_periods ADD CONSTRAINT billing_periods_estado_check CHECK (estado IN ('abierto', 'facturado', 'cerrado'))");

        // mes 1-12
        DB::statement('ALTER TABLE billing_periods ADD CONSTRAINT billing_periods_mes_check CHECK (mes >= 1 AND mes <= 12)');

        // UNIQUE(condominium_id, anio, mes) WHERE deleted_at IS NULL (PANORAMA §4)
        DB::statement('CREATE UNIQUE INDEX billing_periods_condominium_anio_mes_unique ON billing_periods (condominium_id, anio, mes) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_periods');
    }
};
