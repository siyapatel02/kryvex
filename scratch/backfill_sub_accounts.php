<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "=== Backfilling Default Trading Accounts ===\n";

$users = DB::connection('mongodb')->table('users')->get();
$countCreated = 0;

foreach ($users as $user) {
    $userArr = (array) $user;
    $userUid = $userArr['uid'] ?? null;
    if (!$userUid) continue;

    // Get user main wallet
    $wallet = (array) DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->first();

    // Check if default trading account exists
    $defaultAccount = DB::connection('mongodb')
        ->table('trading_accounts')
        ->where('user_uid', $userUid)
        ->where('is_default', true)
        ->first();

    if (!$defaultAccount) {
        $accountUid = (string) Str::uuid();
        $avail = (float) ($wallet['demo_available_balance'] ?? 0.0);
        $locked = (float) ($wallet['demo_locked_balance'] ?? 0.0);

        DB::connection('mongodb')->table('trading_accounts')->insert([
            'account_uid'            => $accountUid,
            'user_uid'               => $userUid,
            'account_name'           => 'Main Account',
            'is_default'             => true,
            'demo_available_balance' => $avail,
            'demo_locked_balance'    => $locked,
            'status'                 => 'active',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $defaultAccountUid = $accountUid;
        $countCreated++;
        echo "Created Main Account for User UID: {$userUid} (Account UID: {$accountUid})\n";
    } else {
        $defaultAccountArr = (array) $defaultAccount;
        $defaultAccountUid = $defaultAccountArr['account_uid'];
    }

    // Link existing orders, positions, trades, history, ledgers without account_uid to defaultAccountUid
    $tables = ['orders', 'positions', 'trades', 'history', 'wallet_ledgers', 'option_orders', 'option_positions', 'option_trades'];
    foreach ($tables as $table) {
        DB::connection('mongodb')
            ->table($table)
            ->where('user_uid', $userUid)
            ->whereNull('account_uid')
            ->update(['account_uid' => $defaultAccountUid]);
    }
}

echo "=== Backfill Complete! Created {$countCreated} accounts. ===\n";
