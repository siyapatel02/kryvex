<?php
function fetchLiveFxEmpirePage($urlPath) {
    $url = 'https://www.fxempire.com' . $urlPath;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    $html = curl_exec($ch);

    $articles = [];
    if (!$html) return $articles;

    if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $m)) {
        $json = json_decode($m[1], true);
        $queries = $json['props']['pageProps']['dehydratedState']['queries'] ?? [];
        foreach ($queries as $q) {
            $queryHash = $q['queryHash'] ?? '';
            if (is_string($queryHash) && str_contains($queryHash, 'articles')) {
                $data = $q['state']['data'] ?? [];
                $rawList = [];
                if (isset($data['articles']) && is_array($data['articles'])) {
                    $rawList = $data['articles'];
                } elseif (isset($data['items']) && is_array($data['items'])) {
                    $rawList = $data['items'];
                } elseif (is_array($data)) {
                    $rawList = $data;
                }

                foreach ($rawList as $item) {
                    if (!is_array($item) || !isset($item['title'])) continue;
                    
                    $title = $item['title'];
                    $slug = $item['articleUrl'] ?? ('/forecasts/article/' . ($item['slug'] ?? ''));
                    $link = str_starts_with($slug, 'http') ? $slug : ('https://www.fxempire.com' . $slug);
                    
                    // REAL IMAGE URL FROM FXEMPIRE
                    $img = $item['imageUrl'] ?? null;
                    if (!$img && isset($item['media'])) {
                        $img = 'https://responsive.fxempire.com/v7/_fxempire_' . $item['media'];
                    }

                    $dateTs = isset($item['date']) ? ($item['date'] / 1000) : time();
                    $dateStr = date('M j, Y', $dateTs);

                    $authorName = $item['author']['name'] ?? 'FXEmpire Analyst';

                    $articles[] = [
                        'title' => $title,
                        'link' => $link,
                        'image' => $img,
                        'date' => $dateStr,
                        'author' => $authorName,
                        'description' => $item['seoTitle'] ?? $title,
                    ];
                }
            }
        }
    }

    return $articles;
}

$forecasts = fetchLiveFxEmpirePage('/forecasts');
echo "Live Forecasts fetched: " . count($forecasts) . "\n\n";

for ($i = 0; $i < min(5, count($forecasts)); $i++) {
    $f = $forecasts[$i];
    echo ($i + 1) . ": Title: " . $f['title'] . "\n";
    echo "   Author: " . $f['author'] . " | Date: " . $f['date'] . "\n";
    echo "   Link: " . $f['link'] . "\n";
    echo "   REAL Image: " . $f['image'] . "\n\n";
}
