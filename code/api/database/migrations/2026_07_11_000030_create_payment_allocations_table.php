<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('payment_receipt_id');
            $table->uuid('invoice_id');
            $table->decimal('valor_aplicado', 15, 2);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('payment_receipt_id')
                ->references('id')
                ->on('payment_receipts')
                ->onDelete('cascade');

            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index('invoice_id', 'payment_allocations_invoice_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
