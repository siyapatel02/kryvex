<?php

define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

$userUid = '80eb3e58-f9cf-4352-941c-c25b1a548522';
$filePath = storage_path('app/private/kyc/' . $userUid . '/front.jpg');

if (!file_exists($filePath)) {
    die("File does not exist at $filePath\n");
}

$apiKey = env('OCR_SPACE_API_KEY', 'helloworld');
$apiUrl = 'https://api.ocr.space/parse/image';

echo "Sending OCR request for $filePath...\n";

try {
    $response = Http::timeout(20)
        ->attach('file', file_get_contents($filePath), 'front.jpg')
        ->post($apiUrl, [
            'apikey' => $apiKey,
            'language' => 'eng',
            'isOverlayRequired' => 'false',
        ]);

    if ($response->successful()) {
        $json = $response->json();
        $text = $json['ParsedResults'][0]['ParsedText'] ?? '';
        echo "=== Parsed OCR Text ===\n";
        echo $text . "\n";
        echo "=======================\n";
    } else {
        echo "OCR API request failed with status: " . $response->status() . "\n";
        echo $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
