<?php
/**
 * Offline Aadhaar XML Decryption & Parsing Test Script
 * 
 * Instructions:
 * 1. Copy your downloaded Aadhaar ZIP file into this directory.
 * 2. Rename it to "aadhaar.zip" (or change the $zipFile variable below).
 * 3. Update the 4-digit $shareCode variable with the code you chose on the UIDAI portal.
 * 4. Run the script: php test_aadhaar.php
 */

$zipFile = __DIR__ . '/aadhaar.zip';
$shareCode = '1234'; // <-- REPLACE with your 4-digit Share Code

echo "Checking for ZIP file: $zipFile...\n";

if (!file_exists($zipFile)) {
    die("Error: ZIP file not found! Please place your Aadhaar ZIP file in this directory and name it 'aadhaar.zip'.\n");
}

$zip = new ZipArchive;

if ($zip->open($zipFile) === TRUE) {
    echo "Opening ZIP file... Setting password (Share Code): $shareCode\n";
    
    // Set decryption password (which is the Share Code)
    $zip->setPassword($shareCode);
    
    // Find the XML file inside the ZIP
    $xmlFileName = null;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (pathinfo($name, PATHINFO_EXTENSION) === 'xml') {
            $xmlFileName = $name;
            break;
        }
    }
    
    if (!$xmlFileName) {
        die("Error: No XML file found inside the ZIP.\n");
    }
    
    echo "Found XML file: $xmlFileName. Decrypting...\n";
    
    // Read the decrypted XML content from ZIP
    $xmlContent = $zip->getFromName($xmlFileName);
    $zip->close();
    
    if ($xmlContent === false) {
        die("Error: Failed to decrypt ZIP file. Please check if your Share Code is correct.\n");
    }
    
    echo "Decryption successful! Parsing XML...\n";
    
    // Load and parse XML content
    // UIDAI Offline XML uses namespaces, so we register them or parse cleanly
    $xml = simplexml_load_string($xmlContent);
    if ($xml === false) {
        die("Error: Failed to parse XML structure.\n");
    }
    
    // Extracted elements from UIDAI XML Schema
    $poi = $xml->UidData->Poi; // Person of Interest Info
    $poa = $xml->UidData->Poa; // Person of Address Info
    $photoBase64 = (string)$xml->UidData->Pht; // Profile Photo Base64
    
    echo "\n============================================\n";
    echo "    AADHAAR DETAILS EXTRACTED SUCCESSFULLY  \n";
    echo "============================================\n";
    echo "Name:      " . ($poi['name'] ?? 'N/A') . "\n";
    echo "DOB:       " . ($poi['dob'] ?? 'N/A') . "\n";
    echo "Gender:    " . ($poi['gender'] ?? 'N/A') . "\n";
    echo "--------------------------------------------\n";
    echo "Address Details:\n";
    echo "  Care of: " . ($poa['co'] ?? 'N/A') . "\n";
    echo "  Locality: " . ($poa['loc'] ?? 'N/A') . "\n";
    echo "  District: " . ($poa['dist'] ?? 'N/A') . "\n";
    echo "  State:    " . ($poa['state'] ?? 'N/A') . "\n";
    echo "  Pincode:  " . ($poa['pc'] ?? 'N/A') . "\n";
    echo "============================================\n";
    
    if ($photoBase64) {
        $photoPath = __DIR__ . '/extracted_photo.jpg';
        file_put_contents($photoPath, base64_decode($photoBase64));
        echo "\nSuccess: Saved official profile photo to: $photoPath\n";
    }
    
} else {
    echo "Error: Failed to open ZIP file.\n";
}
