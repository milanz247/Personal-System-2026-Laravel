<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These three tables were hard-deleting rows with no recovery path and no trail.
     * Users stay hard-deleted (right-to-erasure requests should actually purge data),
     * but accounts/transactions/categories now soft-delete so a mistaken or malicious
     * delete is recoverable and historical records aren't destroyed outright.
     */
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Include deleted_at in the uniqueness check so a soft-deleted category no
        // longer blocks creating a new one with the same name/type. The new index is
        // added before the old one is dropped because user_id's foreign key needs a
        // supporting index at all times — MySQL rejects dropping the only one covering it.
        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['user_id', 'name', 'type', 'deleted_at'], 'categories_user_id_name_type_deleted_at_unique');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_user_id_name_type_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['user_id', 'name', 'type'], 'categories_user_id_name_type_unique');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_user_id_name_type_deleted_at_unique');
        });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
