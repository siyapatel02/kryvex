<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$clientId = env('DIGIO_CLIENT_ID');
$clientSecret = env('DIGIO_CLIENT_SECRET');

$reqId = 'RID260724100347510MMHHWQLLBWZ6GO';

$urls = [
    "https://ext.digio.in:444/v2/client/digilocker/{$reqId}",
    "https://ext.digio.in:444/v2/client/digilocker/{$reqId}/response",
    "https://ext.digio.in:444/v2/client/digilocker/request/{$reqId}",
];

foreach ($urls as $url) {
    $response = Http::withoutVerifying()
        ->withBasicAuth($clientId, $clientSecret)
        ->get($url);

    echo "URL: {$url} => Status: " . $response->status() . " Body: " . substr($response->body(), 0, 300) . "\n";
}
