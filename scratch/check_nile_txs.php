<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$addr = 'TUEZSdKsoDHQMeZwihtdoBiN46zxhGWYdH';
$nileUrl = 'https://nile.trongrid.io';

echo "=== TRC20 Transfers for $addr ===\n";
$res1 = Http::get("$nileUrl/v1/accounts/$addr/transactions/trc20?limit=20")->json();
echo json_encode($res1['data'] ?? [], JSON_PRETTY_PRINT);

echo "\n\n=== All Transactions for $addr ===\n";
$res2 = Http::get("$nileUrl/v1/accounts/$addr/transactions?limit=20")->json();
echo json_encode($res2['data'] ?? [], JSON_PRETTY_PRINT);
