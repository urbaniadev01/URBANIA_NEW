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
        Schema::table('contacts', function (Blueprint $table): void {
            $table->uuid('organization_id')->nullable()->after('id');
            $table->uuid('created_by')->nullable()->after('telefono');
            $table->uuid('updated_by')->nullable()->after('created_by');
        });

        // Backfill: every existing contact has a user_id — inherit that user's organization_id.
        DB::statement('
            UPDATE contacts
            SET organization_id = users.organization_id
            FROM users
            WHERE users.id = contacts.user_id
        ');

        Schema::table('contacts', function (Blueprint $table): void {
            $table->uuid('organization_id')->nullable(false)->change();
            $table->uuid('user_id')->nullable()->change();

            $table->foreign('organization_id')
                ->references('id')
                ->on('organizations')
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

        // R-DIR-02: a contact may exist without a user_id (e.g. an absent owner).
        // Replace the plain unique index on user_id with a partial one so multiple
        // NULLs don't collide, while still enforcing uniqueness when user_id IS NOT NULL.
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropUnique('contacts_user_id_unique');
        });
        DB::statement('CREATE UNIQUE INDEX contacts_user_id_unique ON contacts (user_id) WHERE user_id IS NOT NULL');
    }

    public function down(): void
    {
        // R-DIR-02 (up()) intentionally allows user_id IS NULL — a rollback that
        // restores the NOT NULL constraint cannot coexist with that data. Fail
        // loudly with an actionable message instead of a raw constraint violation.
        $orphanedContacts = DB::table('contacts')->whereNull('user_id')->count();
        if ($orphanedContacts > 0) {
            throw new RuntimeException(
                "Cannot roll back: {$orphanedContacts} contact(s) have user_id IS NULL ".
                '(created without a linked user, per R-DIR-02). Reassign or remove them '.
                'before rolling back this migration.',
            );
        }

        DB::statement('DROP INDEX IF EXISTS contacts_user_id_unique');

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->uuid('user_id')->nullable(false)->change();
            $table->unique('user_id');
            $table->dropColumn(['organization_id', 'created_by', 'updated_by']);
        });
    }
};
