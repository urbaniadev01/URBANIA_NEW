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
        Schema::create('payment_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('condominium_id');
            $table->uuid('property_id');
            $table->uuid('contact_id');
            $table->decimal('valor', 15, 2);
            $table->date('fecha');
            $table->text('medio');
            $table->text('referencia')->nullable();
            $table->text('soporte_url')->nullable();
            $table->uuid('created_by');
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

            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('restrict');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        // R-COB-15: medio de pago cerrado en Fase 1 — efectivo/banco únicamente
        DB::statement("ALTER TABLE payment_receipts ADD CONSTRAINT payment_receipts_medio_check CHECK (medio IN ('efectivo', 'banco'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
