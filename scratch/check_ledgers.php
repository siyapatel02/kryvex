<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$userUid = 'ee57787c-786c-4378-8659-f3f8db984bb1';

$ledgers = DB::connection('mongodb')
    ->table('wallet_ledgers')
    ->where('user_uid', $userUid)
    ->orderBy('created_at', 'desc')
    ->limit(20)
    ->get();

echo "--- LEDGER ENTRIES ---\n";
foreach ($ledgers as $ledger) {
    $ledger = (array) $ledger;
    echo "Time: " . (is_object($ledger['created_at']) ? json_encode($ledger['created_at']) : $ledger['created_at']) . "\n";
    echo "Type: " . $ledger['type'] . " | Category: " . ($ledger['category'] ?? 'N/A') . "\n";
    echo "Direction: " . $ledger['direction'] . " | Amount: " . $ledger['amount'] . " | Balance After: " . $ledger['balance_after'] . "\n";
    echo "Note: " . ($ledger['note'] ?? 'N/A') . "\n";
    echo "-----------------------------\n";
}
