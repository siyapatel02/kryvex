<?php
require __DIR__ . '/../vendor/autoload.php';

$url = 'https://news.google.com/rss/search?q=site:fxempire.com+prediction+OR+forecast+OR+market&hl=en-US&gl=US&ceid=US:en';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$body = curl_exec($ch);
curl_close($ch);

$xml = @simplexml_load_string($body);
if ($xml && isset($xml->channel->item)) {
    echo "Count: " . count($xml->channel->item) . "\n";
    $i = 0;
    foreach ($xml->channel->item as $item) {
        if ($i >= 10) break;
        echo ($i + 1) . ": " . (string)$item->title . "\n";
        echo "   Link: " . (string)$item->link . "\n";
        $i++;
    }
} else {
    echo "Failed to load XML\n";
}
