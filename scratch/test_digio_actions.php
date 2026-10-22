<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$clientId = env('DIGIO_CLIENT_ID');
$clientSecret = env('DIGIO_CLIENT_SECRET');

// Test Payload 3: Action 1 = digilocker (aadhaar), Action 2 = pan
$payload3 = [
    'customer_identifier' => 'testpan3@kryvex.com',
    'customer_name' => 'Test User',
    'notify_customer' => false,
    'expire_in_days' => 7,
    'generate_access_token' => true,
    'actions' => [
        [
            'type' => 'digilocker',
            'action_ref' => 'digilocker-1',
            'title' => 'DigiLocker Aadhaar Verification',
            'document_types' => ['aadhaar'],
        ]
    ]
];

$res3 = Illuminate\Support\Facades\Http::withoutVerifying()
    ->withBasicAuth($clientId, $clientSecret)
    ->post("https://ext.digio.in:444/client/kyc/v2/request", $payload3);

echo "--- TEST 3 RESPONSE ---\n";
print_r($res3->json());
