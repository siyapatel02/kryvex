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
    
    foreach ($queries as $q) {
        $keyStr = json_encode($q);
        if (str_contains($keyStr, 'articles')) {
            $data = $q['state']['data'] ?? [];
            $articles = $data['articles'] ?? $data['items'] ?? (is_array($data) ? $data : []);
            
            echo "Found articles! Count: " . count($articles) . "\n\n";
            for ($i = 0; $i < min(10, count($articles)); $i++) {
                $a = $articles[$i];
                $title = $a['title'] ?? 'N/A';
                $slug = $a['articleUrl'] ?? ('/forecasts/article/' . ($a['slug'] ?? ''));
                $link = 'https://www.fxempire.com' . $slug;
                
                // Real Image URL from FXEmpire
                $img = $a['imageUrl'] ?? null;
                if (!$img && isset($a['media'])) {
                    $img = 'https://responsive.fxempire.com/v7/_fxempire_' . $a['media'];
                }

                $author = $a['author']['name'] ?? 'FXEmpire Analyst';
                $dateStr = isset($a['date']) ? date('M j, Y', $a['date'] / 1000) : date('M j, Y');

                echo ($i + 1) . ": " . $title . "\n";
                echo "   Author: " . $author . " | Date: " . $dateStr . "\n";
                echo "   Link: " . $link . "\n";
                echo "   REAL IMAGE: " . $img . "\n\n";
            }
        }
    }
}
