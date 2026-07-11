<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('invoice_id');
            $table->uuid('charge_concept_id');
            $table->text('descripcion')->nullable();
            $table->decimal('valor', 15, 2);
            $table->decimal('base_calculo', 5, 4)->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');

            $table->foreign('charge_concept_id')
                ->references('id')
                ->on('charge_concepts')
                ->onDelete('restrict');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('invoice_id', 'invoice_items_invoice_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
