<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$addr = 'TUEZSdKsoDHQMeZwihtdoBiN46zxhGWYdH';
$nileUrl = 'https://nile.trongrid.io';

// Check incoming TRC20 to TUEZSdKsoDHQMeZwihtdoBiN46zxhGWYdH with no contract filter
$res = Http::get("$nileUrl/v1/accounts/$addr/transactions/trc20?limit=5&only_to=true")->json();
echo "Latest TRC20 to address:\n";
foreach ($res['data'] ?? [] as $tx) {
    echo "TxID: " . $tx['transaction_id'] . "\n";
    echo "Contract: " . ($tx['token_info']['address'] ?? 'none') . " (" . ($tx['token_info']['symbol'] ?? '') . ")\n";
    echo "From: " . $tx['from'] . "\n";
    echo "To: " . $tx['to'] . "\n";
    echo "Amount: " . ($tx['value'] / pow(10, $tx['token_info']['decimals'] ?? 6)) . "\n";
    echo "Time: " . date('Y-m-d H:i:s', $tx['block_timestamp']/1000) . "\n\n";
}
