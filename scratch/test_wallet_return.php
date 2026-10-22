<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$walletService = app(\App\Services\DemoWalletService::class);
$u = DB::connection('mongodb')->table('users')->where('email', 'siyaptl02@gmail.com')->first();
$w = $walletService->getOrCreateWallet($u->uid);

echo "=== RETURNED WALLET DATA FROM DemoWalletService ===\n";
echo "demo_main_balance: " . ($w['demo_main_balance'] ?? 0) . "\n";
echo "real_main_balance: " . ($w['real_main_balance'] ?? 0) . "\n";
echo "real_available_balance: " . ($w['real_available_balance'] ?? 0) . "\n";
echo "crypto_balances: " . json_encode($w['crypto_balances'] ?? []) . "\n";
echo "real_crypto_balances: " . json_encode($w['real_crypto_balances'] ?? []) . "\n";
echo "demo_crypto_balances: " . json_encode($w['demo_crypto_balances'] ?? []) . "\n";
