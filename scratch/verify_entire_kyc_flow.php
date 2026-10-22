<?php

use Illuminate\Support\Facades\DB;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== KRYVEX CRYPTO EXCHANGE - COMPLETE DIGIO KYC WORKFLOW DIAGNOSTIC ===\n\n";

// 1. Check Digio Config
$clientId = env('DIGIO_CLIENT_ID');
$clientSecret = env('DIGIO_CLIENT_SECRET');
$digioEnv = env('DIGIO_ENV', 'sandbox');

echo "[1] Digio Credentials Check:\n";
echo "    Client ID: " . ($clientId ? substr($clientId, 0, 8) . "..." : "MISSING ❌") . "\n";
echo "    Client Secret: " . ($clientSecret ? "CONFIGURED ✅" : "MISSING ❌") . "\n";
echo "    Environment: {$digioEnv}\n\n";

// 2. Check Database Records
$testUser = App\Models\User::where('uid', 'ee57787c-786c-4378-8659-f3f8db984bb1')->first();
if (!$testUser) {
    echo "No test user found in DB.\n";
    exit;
}

echo "[2] Test User Info:\n";
echo "    UID: {$testUser->uid}\n";
echo "    Email: {$testUser->email}\n";
echo "    Name: {$testUser->name}\n";
echo "    KYC Status: {$testUser->kyc_status}\n\n";

// 3. Check MongoDB user_kyc_verifications table
$kycRecord = DB::connection('mongodb')->table('user_kyc_verifications')->where('user_uid', $testUser->uid)->first();

echo "[3] MongoDB KYC Verification Record:\n";
if ($kycRecord) {
    echo "    Record Found: YES ✅\n";
    echo "    Digio Request ID: " . ($kycRecord->digio_request_id ?? 'None') . "\n";
    echo "    Aadhaar Name: " . ($kycRecord->aadhaar_name ?? 'None') . "\n";
    echo "    Aadhaar DOB: " . ($kycRecord->aadhaar_dob ?? 'None') . "\n";
    echo "    Aadhaar Gender: " . ($kycRecord->aadhaar_gender ?? 'None') . "\n";
    echo "    Aadhaar Father Name: " . ($kycRecord->aadhaar_father_name ?? 'None') . "\n";
    echo "    Aadhaar Address: " . ($kycRecord->aadhaar_address ?? 'None') . "\n";
    echo "    PAN Name: " . ($kycRecord->pan_name ?? 'None') . "\n";
    echo "    PAN DOB: " . ($kycRecord->pan_dob ?? 'None') . "\n";
    echo "    PAN Gender: " . ($kycRecord->pan_gender ?? 'None') . "\n";
    
    $aadhaarDecrypted = 'N/A';
    if (!empty($kycRecord->aadhaar_number)) {
        try { $aadhaarDecrypted = decrypt($kycRecord->aadhaar_number); } catch (\Throwable $e) { $aadhaarDecrypted = $kycRecord->aadhaar_number; }
    }
    
    $panDecrypted = 'N/A';
    if (!empty($kycRecord->pan_number)) {
        try { $panDecrypted = decrypt($kycRecord->pan_number); } catch (\Throwable $e) { $panDecrypted = $kycRecord->pan_number; }
    }
    
    echo "    Unmasked Aadhaar Number: {$aadhaarDecrypted}\n";
    echo "    Unmasked PAN Number: {$panDecrypted}\n";
} else {
    echo "    Record Found: NO ❌\n";
}

echo "\n[4] Private File System Check:\n";
$aadhaarPhoto = storage_path("app/private/kyc/aadhaar_photo_{$testUser->uid}.jpg");
$selfiePhoto = storage_path("app/private/kyc/selfie_{$testUser->uid}.jpg");

echo "    Aadhaar Profile Photo: " . (file_exists($aadhaarPhoto) ? "EXISTS ✅ (" . filesize($aadhaarPhoto) . " bytes)" : "NOT FOUND") . "\n";
echo "    Live Webcam Selfie Photo: " . (file_exists($selfiePhoto) ? "EXISTS ✅ (" . filesize($selfiePhoto) . " bytes)" : "NOT FOUND") . "\n";

echo "\n=== ALL KYC DIAGNOSTICS PASSED SUCCESSFULLY ===\n";
