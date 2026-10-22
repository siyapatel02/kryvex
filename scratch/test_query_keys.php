<?php
$url = 'https://www.fxempire.com/forecasts';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 6);
$html = curl_exec($ch);

if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $m)) {
    $json = json_decode($m[1], true);
    $queries = $json['props']['pageProps']['dehydratedState']['queries'] ?? [];
    echo "Queries count: " . count($queries) . "\n";
    foreach ($queries as $i => $q) {
        $key = json_encode($q['queryKey'] ?? $q['queryHash'] ?? '');
        echo $i . ": " . $key . "\n";
    }
} else {
    echo "Failed to match NEXT_DATA\n";
}
