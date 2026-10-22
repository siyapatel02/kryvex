<?php
$data = json_decode(file_get_contents(__DIR__ . '/q_data.json'), true);

function inspectData($arr, $path = '') {
    if (is_array($arr)) {
        if (isset($arr[0]['title']) && isset($arr[0]['imageUrl'])) {
            echo "SUCCESS! Found array of articles at path: $path (Count: " . count($arr) . ")\n";
            foreach (array_slice($arr, 0, 5) as $i => $item) {
                echo " " . ($i + 1) . ": Title: " . $item['title'] . "\n";
                echo "    Image: " . ($item['imageUrl'] ?? 'N/A') . "\n";
                echo "    ArticleUrl: " . ($item['articleUrl'] ?? 'N/A') . "\n";
            }
            return;
        }
        foreach ($arr as $k => $v) {
            inspectData($v, $path . ' > ' . $k);
        }
    }
}

inspectData($data, 'root');
