<?php
$json = json_decode(file_get_contents(__DIR__ . '/next_data.json'), true);

$queries = $json['props']['pageProps']['dehydratedState']['queries'] ?? [];
foreach ($queries as $q) {
    if (isset($q['queryHash']) && str_contains($q['queryHash'], 'articles')) {
        $data = $q['state']['data'] ?? [];
        echo "Data keys: " . implode(', ', array_keys($data)) . "\n";
        print_r($data);
    }
}
