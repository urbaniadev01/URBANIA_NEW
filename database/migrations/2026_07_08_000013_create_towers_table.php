<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('towers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('condominium_id');
            $table->text('nombre');
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

            $table->unique(['condominium_id', 'nombre'], 'towers_condominium_nombre_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('towers');
    }
};
