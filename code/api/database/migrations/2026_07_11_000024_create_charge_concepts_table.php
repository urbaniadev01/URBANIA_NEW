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
        Schema::create('charge_concepts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('condominium_id');
            $table->text('nombre');
            $table->text('tipo');
            $table->text('metodo_calculo');
            $table->decimal('valor_base', 15, 2);
            $table->boolean('activo')->default(true);
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

        // R-COB (decisión 2 de PANORAMA §4): CHECK constraint — closed enum for tipo
        DB::statement("ALTER TABLE charge_concepts ADD CONSTRAINT charge_concepts_tipo_check CHECK (tipo IN ('administracion', 'fondo_imprevistos', 'multa', 'extraordinaria'))");

        // CHECK constraint — closed enum for metodo_calculo
        DB::statement("ALTER TABLE charge_concepts ADD CONSTRAINT charge_concepts_metodo_calculo_check CHECK (metodo_calculo IN ('coeficiente', 'fijo', 'por_area', 'manual'))");

        // UNIQUE(condominium_id, nombre) WHERE deleted_at IS NULL (PANORAMA §4)
        DB::statement('CREATE UNIQUE INDEX charge_concepts_condominium_nombre_unique ON charge_concepts (condominium_id, nombre) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_concepts');
    }
};
