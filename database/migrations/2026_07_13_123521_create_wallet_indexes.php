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
        Schema::table('wallets', function (Blueprint $collection) {
            $collection->unique('user_uid');
        });

        Schema::table('wallet_ledgers', function (Blueprint $collection) {
            $collection->index('user_uid');
            $collection->index('type');
            $collection->index('direction');
            $collection->index('created_at');
        });

        Schema::table('wallet_withdrawal_requests', function (Blueprint $collection) {
            $collection->index('user_uid');
            $collection->index('status');
            $collection->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $collection) {
            $collection->dropUnique(['user_uid']);
        });

        Schema::table('wallet_ledgers', function (Blueprint $collection) {
            $collection->dropIndex(['user_uid']);
            $collection->dropIndex(['type']);
            $collection->dropIndex(['direction']);
            $collection->dropIndex(['created_at']);
        });

        Schema::table('wallet_withdrawal_requests', function (Blueprint $collection) {
            $collection->dropIndex(['user_uid']);
            $collection->dropIndex(['status']);
            $collection->dropIndex(['created_at']);
        });
    }
};
