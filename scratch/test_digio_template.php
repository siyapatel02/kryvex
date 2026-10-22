<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$clientId = env('DIGIO_CLIENT_ID');
$clientSecret = env('DIGIO_CLIENT_SECRET');

$payload = [
    'customer_identifier' => 'testtemplate@kryvex.com',
    'customer_name' => 'Test User',
    'template_name' => 'DIGILOCKER',
    'notify_customer' => false,
    'expire_in_days' => 7,
    'generate_access_token' => true,
    'reference_id' => 'kyc_test_' . time(),
    'transaction_id' => 'tx_' . time(),
];

$res = Illuminate\Support\Facades\Http::withoutVerifying()
    ->withBasicAuth($clientId, $clientSecret)
    ->post("https://ext.digio.in:444/client/kyc/v2/request/with_template", $payload);

echo "--- TEMPLATE REQUEST RESPONSE ---\n";
print_r($res->json());
