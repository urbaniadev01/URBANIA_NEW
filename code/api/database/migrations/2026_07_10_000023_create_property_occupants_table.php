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
        Schema::create('property_occupants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('contact_id');
            $table->uuid('property_id');
            $table->uuid('occupant_type_id');
            $table->boolean('es_principal')->default(false);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('property_id');
            $table->index('occupant_type_id');

            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('cascade');

            $table->foreign('property_id')
                ->references('id')
                ->on('properties')
                ->onDelete('cascade');

            $table->foreign('occupant_type_id')
                ->references('id')
                ->on('occupant_types')
                ->onDelete('restrict');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        // R-DIR-11: a contact cannot have the same occupant type on the same property twice (while active).
        DB::statement('
            CREATE UNIQUE INDEX property_occupants_contact_property_type_unique
            ON property_occupants (contact_id, property_id, occupant_type_id)
            WHERE deleted_at IS NULL
        ');

        // R-DIR-07: only one principal occupant per property+occupant_type (while active).
        DB::statement('
            CREATE UNIQUE INDEX property_occupants_principal_unique
            ON property_occupants (property_id, occupant_type_id)
            WHERE es_principal = true AND deleted_at IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('property_occupants');
    }
};
