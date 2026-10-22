<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wallet_ledgers', function (Blueprint $collection) {
            $collection->index(['user_uid', 'created_at']);
        });

        Schema::table('wallet_withdrawal_requests', function (Blueprint $collection) {
            $collection->index(['user_uid', 'status', 'created_at']);
        });

        Schema::table('futures_orders', function (Blueprint $collection) {
            $collection->index(['user_uid', 'status']);
        });

        Schema::table('options_orders', function (Blueprint $collection) {
            $collection->index(['user_uid', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_ledgers', function (Blueprint $collection) {
            $collection->dropIndex(['user_uid', 'created_at']);
        });

        Schema::table('wallet_withdrawal_requests', function (Blueprint $collection) {
            $collection->dropIndex(['user_uid', 'status', 'created_at']);
        });

        Schema::table('futures_orders', function (Blueprint $collection) {
            $collection->dropIndex(['user_uid', 'status']);
        });

        Schema::table('options_orders', function (Blueprint $collection) {
            $collection->dropIndex(['user_uid', 'status']);
        });
    }
};
