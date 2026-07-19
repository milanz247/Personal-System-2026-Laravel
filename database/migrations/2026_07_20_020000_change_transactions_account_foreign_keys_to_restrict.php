<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Deleting an account previously cascade-deleted every transaction that ever
     * touched it (as source or destination), silently destroying financial history.
     * Accounts with transaction history must now be blocked from deletion instead.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropForeign(['to_account_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('to_account_id')->references('id')->on('accounts')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['account_id']);
            $table->dropForeign(['to_account_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('to_account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });
    }
};
