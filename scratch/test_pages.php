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
            echo "Query $i hash: $hash\n";
            $data = $q['state']['data'] ?? [];
            echo "Data keys: " . implode(', ', array_keys($data)) . "\n";
            if (isset($data['pages'])) {
                echo "Pages count: " . count($data['pages']) . "\n";
                foreach ($data['pages'] as $pi => $page) {
                    echo " Page $pi keys: " . implode(', ', array_keys($page)) . "\n";
                    if (isset($page['articles'])) {
                        echo " Articles in page $pi: " . count($page['articles']) . "\n";
                        foreach (array_slice($page['articles'], 0, 5) as $ai => $art) {
                            echo "   " . ($ai + 1) . ": " . ($art['title'] ?? 'N/A') . "\n";
                            echo "      Image URL: " . ($art['imageUrl'] ?? 'N/A') . "\n";
                            echo "      Article URL: " . ($art['articleUrl'] ?? 'N/A') . "\n";
                        }
                    }
                }
            }
        }
    }
}
