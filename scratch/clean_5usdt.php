<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$userUid = 'ee57787c-786c-4378-8659-f3f8db984bb1';

// Set clean balance of 5.00 USDT (matching user's actual 5 USDT testnet transaction)
DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->update([
    'real_main_balance' => 5.00,
    'crypto_balances.USDT' => 5.00,
    'updated_at' => now(),
]);

echo "Cleaned wallet to 5.00 USDT.\n";
