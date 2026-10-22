<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$userUid = 'ee57787c-786c-4378-8659-f3f8db984bb1';

$wallet = DB::connection('mongodb')
    ->table('wallets')
    ->where('user_uid', $userUid)
    ->first();

echo "--- WALLET DETAILS ---\n";
if ($wallet) {
    $wallet = (array) $wallet;
    foreach ($wallet as $key => $value) {
        if ($key === '_id') {
            echo "$key: " . (string)$value . "\n";
        } else {
            echo "$key: " . json_encode($value) . "\n";
        }
    }
} else {
    echo "No wallet found.\n";
}
