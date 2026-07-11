<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('condominium_id');
            $table->uuid('property_id');
            $table->uuid('billing_period_id');
            $table->uuid('billing_run_id');
            $table->text('numero');
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');
            $table->decimal('valor_total', 15, 2);
            $table->decimal('saldo', 15, 2);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('condominium_id')
                ->references('id')
                ->on('condominiums')
                ->onDelete('cascade');

            $table->foreign('property_id')
                ->references('id')
                ->on('properties')
                ->onDelete('cascade');

            $table->foreign('billing_period_id')
                ->references('id')
                ->on('billing_periods')
                ->onDelete('cascade');

            $table->foreign('billing_run_id')
                ->references('id')
                ->on('billing_runs')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->unique(['condominium_id', 'numero'], 'invoices_condominium_numero_unique');

            // Índices de PANORAMA §4. `estado` es derivado en lectura (R-COB-08, no es
            // columna), así que el hot path real de "facturas por unidad" es solo property_id;
            // el índice compuesto vive sobre condominium_id+billing_period_id, y
            // fecha_vencimiento por separado (usado para calcular `vencida` en el WHERE).
            $table->index('property_id', 'invoices_property_id_index');
            $table->index(['condominium_id', 'billing_period_id'], 'invoices_condominium_billing_period_index');
            $table->index('fecha_vencimiento', 'invoices_fecha_vencimiento_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
