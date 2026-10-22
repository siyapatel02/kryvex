<?php
$json = json_decode(file_get_contents(__DIR__ . '/next_data.json'), true);

echo "Keys: " . implode(', ', array_keys($json)) . "\n";
if (isset($json['props']['pageProps'])) {
    echo "pageProps keys: " . implode(', ', array_keys($json['props']['pageProps'])) . "\n";
}

function printKeys($arr, $prefix = '') {
    if (!is_array($arr)) return;
    foreach ($arr as $k => $v) {
        if (is_array($v)) {
            echo $prefix . $k . " (array, " . count($v) . " items)\n";
            if (count($v) < 10 && strlen($prefix) < 8) {
                printKeys($v, $prefix . '  ');
            }
        } else {
            if (strlen((string)$v) < 100) {
                echo $prefix . $k . " => " . $v . "\n";
            }
        }
    }
}

if (isset($json['props']['pageProps'])) {
    printKeys($json['props']['pageProps'], '  ');
}
