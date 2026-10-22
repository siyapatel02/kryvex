<?php
require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\Http;

function fetchFxEmpireDirectArticles($urlPath = '/forecasts', $prefix = 'res')
{
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

        $count = 1;
        foreach ($queries as $q) {
            $hash = json_encode($q['queryKey'] ?? '');
            if (str_contains($hash, 'articles')) {
                $rawArticles = $q['state']['data']['data']['articles'] ?? $q['state']['data']['articles'] ?? [];
                if (!is_array($rawArticles)) continue;

                foreach ($rawArticles as $item) {
                    if (!is_array($item) || empty($item['title'])) continue;

                    $title = trim($item['title']);
                    $slug = $item['articleUrl'] ?? ('/forecasts/article/' . ($item['slug'] ?? ''));
                    $link = str_starts_with($slug, 'http') ? $slug : ('https://www.fxempire.com' . $slug);

                    // REAL FXEMPIRE IMAGE
                    $imageUrl = $item['imageUrl'] ?? null;
                    if (!$imageUrl && isset($item['media'])) {
                        $imageUrl = 'https://responsive.fxempire.com/v7/_fxempire_' . $item['media'];
                    }

                    $dateTs = isset($item['date']) ? ($item['date'] / 1000) : (isset($item['timestamp']) ? ($item['timestamp'] / 1000) : time());
                    $formattedDate = date('M j, Y', $dateTs);

                    $authorName = $item['author']['name'] ?? 'FXEmpire Analyst';
                    $cleanDesc = $item['seoTitle'] ?? $title;

                    $t = mb_strtolower($title);
                    $category = 'Price Prediction';
                    if (str_contains($t, 'prediction') || str_contains($t, 'forecast') || str_contains($t, 'target')) {
                        $category = 'Price Prediction';
                    } elseif (str_contains($t, 'policy') || str_contains($t, 'sec') || str_contains($t, 'bill') || str_contains($t, 'fomc')) {
                        $category = 'Policy';
                    } elseif (str_contains($t, 'bitcoin') || str_contains($t, 'crypto') || str_contains($t, 'eth') || str_contains($t, 'ethereum')) {
                        $category = 'Crypto';
                    } elseif (str_contains($t, 'oil') || str_contains($t, 'gold') || str_contains($t, 'silver') || str_contains($t, 'gas')) {
                        $category = 'Commodities';
                    } elseif (str_contains($t, 'forex') || str_contains($t, 'dxy') || str_contains($t, 'eur') || str_contains($t, 'gbp')) {
                        $category = 'Forex';
                    } else {
                        $category = 'Markets';
                    }

                    $articles[] = [
                        'id' => $prefix . '-' . $count,
                        'title' => $title,
                        'category' => $category,
                        'date' => $formattedDate,
                        'link' => $link,
                        'description' => $cleanDesc,
                        'full_description' => $cleanDesc,
                        'source' => 'FXEmpire Research (' . $authorName . ')',
                        'image' => $imageUrl
                    ];
                    $count++;
                }
            }
        }
    }

    return $articles;
}

$res = fetchFxEmpireDirectArticles('/forecasts', 'res');
echo "Forecasts articles count: " . count($res) . "\n\n";

for ($i = 0; $i < min(10, count($res)); $i++) {
    $a = $res[$i];
    echo ($i + 1) . ": " . $a['title'] . "\n";
    echo "   Image: " . $a['image'] . "\n";
    echo "   Link: " . $a['link'] . "\n";
    echo "   Source: " . $a['source'] . "\n\n";
}
