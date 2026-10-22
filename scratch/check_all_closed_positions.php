<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$userUid = 'ee57787c-786c-4378-8659-f3f8db984bb1';

$positions = DB::connection('mongodb')
    ->table('futures_positions')
    ->where('user_uid', $userUid)
    ->where('status', 'closed')
    ->get();

echo "--- ALL CLOSED POSITIONS ---\n";
foreach ($positions as $position) {
    $position = (array) $position;
    echo "Symbol: " . $position['symbol'] . " | Side: " . $position['side'] . "\n";
    echo "Entry Price: " . $position['entry_price'] . " | Exit Price: " . ($position['exit_price'] ?? 'null') . " | Qty: " . $position['quantity'] . " | Margin: " . $position['margin'] . "\n";
    echo "Realized PnL: " . ($position['realized_pnl'] ?? 'null') . "\n";
    echo "-----------------------------\n";
}
