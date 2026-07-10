<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('condominium_id');
            $table->uuid('tower_id')->nullable();
            $table->uuid('property_type_id');
            $table->uuid('property_status_id');
            $table->text('codigo');
            $table->integer('piso')->nullable();
            $table->decimal('area_m2', 10, 2)->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('condominium_id')
                ->references('id')
                ->on('condominiums')
                ->onDelete('cascade');

            $table->foreign('tower_id')
                ->references('id')
                ->on('towers')
                ->onDelete('set null');

            $table->foreign('property_type_id')
                ->references('id')
                ->on('property_types')
                ->onDelete('restrict');

            $table->foreign('property_status_id')
                ->references('id')
                ->on('property_statuses')
                ->onDelete('restrict');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            // R-02: Unique code per condominium
            $table->unique(['condominium_id', 'codigo'], 'properties_condominium_codigo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
