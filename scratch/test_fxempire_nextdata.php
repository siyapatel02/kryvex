<?php
$url = 'https://www.fxempire.com/forecasts';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$html = curl_exec($ch);

// Search for __NEXT_DATA__
if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $m)) {
    echo "__NEXT_DATA__ found! Length: " . strlen($m[1]) . "\n";
    $json = json_decode($m[1], true);
    file_put_contents(__DIR__ . '/next_data.json', json_encode($json, JSON_PRETTY_PRINT));
    echo "Saved to next_data.json\n";
} else {
    echo "__NEXT_DATA__ not found. Checking script tags or regex for images...\n";
    if (preg_match_all('/(https:\/\/responsive\.fxempire\.com\/v7\/[^\s"\'<>]+)/i', $html, $matches)) {
        echo "Found responsive.fxempire.com URLs: " . count($matches[1]) . "\n";
        foreach (array_slice(array_unique($matches[1]), 0, 15) as $img) {
            echo " - " . $img . "\n";
        }
    }
}
