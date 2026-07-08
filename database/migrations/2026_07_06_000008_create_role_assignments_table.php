<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->text('scope_type');
            $table->uuid('scope_id')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');

            // Unique constraint: one assignment per (user, role, scope_type, scope_id)
            // COALESCE(scope_id, '00000000-0000-0000-0000-000000000000') → no two rows with same null scope
            $table->unique(
                ['user_id', 'role_id', 'scope_type', 'scope_id'],
                'role_assignments_user_role_scope_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_assignments');
    }
};
