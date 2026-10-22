<?php
$url = 'https://www.fxempire.com/forecasts';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$html = curl_exec($ch);

if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $m)) {
    $json = json_decode($m[1], true);
    $queries = $json['props']['pageProps']['dehydratedState']['queries'] ?? [];
    
    foreach ($queries as $i => $q) {
        $hash = json_encode($q['queryKey'] ?? '');
        if (str_contains($hash, 'forecasts') && str_contains($hash, 'articles')) {
            $innerData = $q['state']['data']['data'] ?? [];
            echo "Inner Data count: " . count($innerData) . "\n\n";
            $idx = 1;
            foreach ($innerData as $art) {
                if (!is_array($art)) continue;
                echo $idx . ": " . ($art['title'] ?? 'N/A') . "\n";
                echo "   Image: " . ($art['imageUrl'] ?? 'N/A') . "\n";
                echo "   Link: https://www.fxempire.com" . ($art['articleUrl'] ?? '') . "\n";
                echo "   Author: " . ($art['author']['name'] ?? 'FXEmpire Analyst') . "\n\n";
                $idx++;
            }
        }
    }
}
