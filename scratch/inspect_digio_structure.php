<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$record = DB::connection('mongodb')->table('user_kyc_verifications')->where('user_uid', 'ee57787c-786c-4378-8659-f3f8db984bb1')->first();

echo "--- MONGODB RECORD ---\n";
print_r($record);

$requestId = $record->digio_request_id ?? 'KID260724121710774I2WX2IYFB5VY5O';
$clientId = env('DIGIO_CLIENT_ID');
$clientSecret = env('DIGIO_CLIENT_SECRET');

$res = Illuminate\Support\Facades\Http::withoutVerifying()
    ->withBasicAuth($clientId, $clientSecret)
    ->post("https://ext.digio.in:444/client/kyc/v2/{$requestId}/response", new stdClass());

echo "\n--- DIGIO RESPONSE ---\n";
$json = $res->json();
print_r($json);
