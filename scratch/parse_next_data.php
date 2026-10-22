<?php
$json = json_decode(file_get_contents(__DIR__ . '/next_data.json'), true);

function findArticles($data, &$articles = []) {
    if (is_array($data)) {
        if (isset($data['title']) && (isset($data['slug']) || isset($data['canonicalUrl']) || isset($data['url'])) && (isset($data['image']) || isset($data['featuredImage']) || isset($data['heroImage']))) {
            $articles[] = $data;
        }
        foreach ($data as $k => $v) {
            findArticles($v, $articles);
        }
    }
    return $articles;
}

$found = findArticles($json);
echo "Articles found in NEXT_DATA: " . count($found) . "\n";
for ($i = 0; $i < min(10, count($found)); $i++) {
    $a = $found[$i];
    echo ($i + 1) . ": Title: " . ($a['title'] ?? 'N/A') . "\n";
    echo "   Slug/URL: " . ($a['slug'] ?? $a['canonicalUrl'] ?? $a['url'] ?? 'N/A') . "\n";
    echo "   Image: " . json_encode($a['image'] ?? $a['featuredImage'] ?? $a['heroImage'] ?? null) . "\n\n";
}
