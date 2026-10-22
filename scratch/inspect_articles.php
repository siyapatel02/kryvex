<?php
$json = json_decode(file_get_contents(__DIR__ . '/next_data.json'), true);

$queries = $json['props']['pageProps']['dehydratedState']['queries'] ?? [];
foreach ($queries as $q) {
    if (isset($q['queryHash']) && str_contains($q['queryHash'], 'articles')) {
        $data = $q['state']['data'] ?? [];
        echo "Found articles query data! Items count: " . count($data) . "\n";
        
        $articles = $data['articles'] ?? $data['items'] ?? $data;
        if (is_array($articles)) {
            echo "Articles list count: " . count($articles) . "\n\n";
            foreach ($articles as $i => $a) {
                if ($i >= 10) break;
                echo ($i + 1) . ": " . ($a['title'] ?? 'N/A') . "\n";
                echo "   Slug: " . ($a['slug'] ?? 'N/A') . "\n";
                echo "   Canonical: " . ($a['canonicalUrl'] ?? $a['path'] ?? 'N/A') . "\n";
                echo "   Category: " . ($a['category']['name'] ?? $a['tag']['name'] ?? 'N/A') . "\n";
                echo "   Image: " . json_encode($a['image'] ?? $a['heroImage'] ?? $a['featuredImage'] ?? null) . "\n\n";
            }
        }
    }
}
