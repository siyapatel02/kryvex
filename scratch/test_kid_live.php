<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$clientId = env('DIGIO_CLIENT_ID');
$clientSecret = env('DIGIO_CLIENT_SECRET');

$kid = "KID260724121710774I2WX2IYFB5VY5O";

$res = Http::withoutVerifying()
    ->withBasicAuth($clientId, $clientSecret)
    ->withHeaders([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])
    ->post("https://ext.digio.in:444/client/kyc/v2/{$kid}/response", new \stdClass());

echo "Status Code: " . $res->status() . "\n";
echo "Response Body:\n" . json_encode($res->json(), JSON_PRETTY_PRINT) . "\n";
