<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$clientId = env('DIGIO_CLIENT_ID');
$clientSecret = env('DIGIO_CLIENT_SECRET');
$digioEnv = env('DIGIO_ENV', 'sandbox');
$templateName = env('DIGIO_KYC_TEMPLATE', 'DIGILOCKER');

$baseUrl = strtolower($digioEnv) === 'production' 
    ? 'https://api.digio.in' 
    : 'https://ext.digio.in:444';

$referenceId = 'kyc_test_' . bin2hex(random_bytes(4));
$transactionId = bin2hex(random_bytes(8));

$payload = [
    'customer_identifier' => 'patelsiya0745@gmail.com',
    'customer_name' => 'Siya Patel',
    'template_name' => $templateName,
    'notify_customer' => false,
    'expire_in_days' => 7,
    'generate_access_token' => true,
    'reference_id' => $referenceId,
    'transaction_id' => $transactionId,
    'generate_deeplink_info' => false,
    'customer_email' => 'patelsiya0745@gmail.com',
];

$response = Http::withoutVerifying()
    ->withBasicAuth($clientId, $clientSecret)
    ->withHeaders([
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ])
    ->post("{$baseUrl}/client/kyc/v2/request/with_template", $payload);

echo "Status Code: " . $response->status() . "\n";
echo "Response Body:\n" . json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";
