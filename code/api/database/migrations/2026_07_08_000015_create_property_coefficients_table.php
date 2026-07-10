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
        Schema::create('property_coefficients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('property_id');
            $table->text('tipo');
            $table->decimal('valor', 5, 4);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('property_id')
                ->references('id')
                ->on('properties')
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

        // R-06-bis: CHECK constraint — closed enum for tipo
        DB::statement("ALTER TABLE property_coefficients ADD CONSTRAINT property_coefficients_tipo_check CHECK (tipo IN ('copropiedad', 'parqueadero', 'deposito', 'mantenimiento'))");

        // PostgreSQL partial unique index: only one active coefficient per property+tipo
        DB::statement('CREATE UNIQUE INDEX property_coefficients_vigente_unique ON property_coefficients (property_id, tipo) WHERE vigente_hasta IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('property_coefficients');
    }
};
