<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$clientId = env('DIGIO_CLIENT_ID');
$clientSecret = env('DIGIO_CLIENT_SECRET');
$digioEnv = env('DIGIO_ENV', 'sandbox');

$baseUrl = strtolower($digioEnv) === 'production' 
    ? 'https://api.digio.in' 
    : 'https://ext.digio.in:444';

// Test creating request then fetching response
$referenceId = 'kyc_test_' . bin2hex(random_bytes(4));
$payload = [
    'customer_identifier' => 'patelsiya0745@gmail.com',
    'customer_name' => 'Siya Patel',
    'template_name' => 'DIGILOCKER',
    'notify_customer' => false,
    'expire_in_days' => 7,
    'generate_access_token' => true,
    'reference_id' => $referenceId,
    'transaction_id' => bin2hex(random_bytes(8)),
    'generate_deeplink_info' => false,
    'customer_email' => 'patelsiya0745@gmail.com',
];

$initRes = Http::withoutVerifying()
    ->withBasicAuth($clientId, $clientSecret)
    ->withHeaders([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])
    ->post("{$baseUrl}/client/kyc/v2/request/with_template", $payload);

$reqId = $initRes->json()['id'] ?? null;
echo "Created Request ID: {$reqId}\n";

if ($reqId) {
    echo "Fetching response via POST /client/kyc/v2/{$reqId}/response ...\n";
    $fetchRes = Http::withoutVerifying()
        ->withBasicAuth($clientId, $clientSecret)
        ->withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
        ->post("{$baseUrl}/client/kyc/v2/{$reqId}/response", new \stdClass());

    echo "Status Code: " . $fetchRes->status() . "\n";
    echo "Response:\n" . json_encode($fetchRes->json(), JSON_PRETTY_PRINT) . "\n";
}
