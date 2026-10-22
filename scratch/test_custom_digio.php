<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$clientId = env('DIGIO_CLIENT_ID');
$clientSecret = env('DIGIO_CLIENT_SECRET');

$payload = [
    'customer_identifier' => 'testuser@kryvex.com',
    'customer_name' => 'Test User',
    'notify_customer' => false,
    'expire_in_days' => 7,
    'generate_access_token' => true,
    'actions' => [
        [
            'type' => 'digilocker',
            'action_ref' => 'digilocker-1',
            'title' => 'DigiLocker Verification',
            'description' => 'Verify Aadhaar & PAN via DigiLocker',
        ],
        [
            'type' => 'id_card',
            'action_ref' => 'pan-1',
            'title' => 'PAN Card Verification',
            'description' => 'Verify PAN Card',
            'sub_actions' => [
                [
                    'type' => 'ocr',
                    'action_ref' => 'pan_ocr',
                    'id_type' => 'pan'
                ]
            ]
        ]
    ]
];

$res = Illuminate\Support\Facades\Http::withoutVerifying()
    ->withBasicAuth($clientId, $clientSecret)
    ->post("https://ext.digio.in:444/client/kyc/v2/request", $payload);

echo "--- DIGIO CUSTOM ACTIONS RESPONSE ---\n";
print_r($res->json());
