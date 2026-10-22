<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$clientId = env('DIGIO_CLIENT_ID');
$clientSecret = env('DIGIO_CLIENT_SECRET');

echo "Testing Digio Production API (api.digio.in) ...\n";
echo "Client ID: {$clientId}\n";

$payload = [
    'customer_identifier' => 'patelsiya0745@gmail.com',
    'identifier' => 'patelsiya0745@gmail.com',
    'customer_name' => 'Siya Patel',
    'document_types' => ['AADHAAR', 'PAN'],
    'redirect_url' => 'http://127.0.0.1:8000/settings?tab=kyc&digio_status=success',
];

$response = Http::withoutVerifying()
    ->withBasicAuth($clientId, $clientSecret)
    ->withHeaders([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])
    ->post('https://api.digio.in/v2/client/digilocker/request', $payload);

echo "Status Code: " . $response->status() . "\n";
echo "Response Body:\n" . json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";
