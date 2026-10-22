<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$userUid = 'ee57787c-786c-4378-8659-f3f8db984bb1';

$position = DB::connection('mongodb')
    ->table('futures_positions')
    ->where('user_uid', $userUid)
    ->where('status', 'closed')
    ->orderBy('closed_at', 'desc')
    ->first();

echo "--- CLOSED POSITION DETAILS ---\n";
if ($position) {
    $position = (array) $position;
    foreach ($position as $key => $value) {
        if ($key === '_id') {
            echo "$key: " . (string)$value . "\n";
        } else {
            echo "$key: " . json_encode($value) . "\n";
        }
    }
} else {
    echo "No closed position found.\n";
}
