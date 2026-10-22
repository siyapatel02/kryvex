<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$records = DB::connection('mongodb')
    ->table('user_kyc_verifications')
    ->orderByDesc('updated_at')
    ->limit(5)
    ->get(['user_uid', 'digio_request_id', 'status', 'updated_at']);

echo "Latest Digio Request IDs (KID):\n";
foreach ($records as $r) {
    echo "User UID: " . ($r->user_uid ?? 'N/A') . " | KID: " . ($r->digio_request_id ?? 'N/A') . " | Status: " . ($r->status ?? 'N/A') . "\n";
}
