<?php
$url = 'https://www.fxempire.com/forecasts';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$html = curl_exec($ch);

echo "HTML length: " . strlen($html) . "\n";

// Search for images and titles inside html
if (preg_match_all('/<a[^>]+href=["\'](\/(?:forecasts|news)\/[^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches)) {
    echo "Found links count: " . count($matches[1]) . "\n";
    for ($i = 0; $i < min(10, count($matches[1])); $i++) {
        echo ($i + 1) . ": " . $matches[1][$i] . "\n";
    }
}

// Search for img tags or json data in HTML
if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $imgMatches)) {
    echo "Found images count: " . count($imgMatches[1]) . "\n";
    for ($i = 0; $i < min(10, count($imgMatches[1])); $i++) {
        echo ($i + 1) . ": " . $imgMatches[1][$i] . "\n";
    }
}
