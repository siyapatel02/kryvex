<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

$s = app(\App\Services\TronCustodyService::class);
$txs = $s->fetchTrc20Transfers('TUEZSdKsoDHQMeZwihtdoBiN46zxhGWYdH');
echo "Transfers count: " . count($txs) . "\n";
foreach ($txs as $t) {
    echo "Tx: " . ($t['transaction_id'] ?? '') . " | Value: " . ($t['value'] ?? 0) / 1000000 . " USDT | Time: " . date('Y-m-d H:i:s', ($t['block_timestamp'] ?? 0)/1000) . "\n";
}

$wallet = \Illuminate\Support\Facades\DB::connection('mongodb')->table('wallets')->where('user_uid', 'ee57787c-786c-4378-8659-f3f8db984bb1')->first();
echo "\nUser Wallet:\n";
echo json_encode($wallet, JSON_PRETTY_PRINT);
