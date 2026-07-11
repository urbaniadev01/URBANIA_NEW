<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peace_certificates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('condominium_id');
            $table->uuid('property_id');
            $table->uuid('emitido_por');
            $table->text('numero');
            $table->date('fecha');
            $table->date('vigente_hasta')->nullable();
            $table->text('pdf_url')->nullable();
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

            $table->foreign('emitido_por')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->unique(['condominium_id', 'numero'], 'peace_certificates_condominium_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peace_certificates');
    }
};
