<?php
$url = 'https://news.google.com/rss/search?q=site:fxempire.com+prediction+OR+forecast+OR+market&hl=en-US&gl=US&ceid=US:en';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$body = curl_exec($ch);

$xml = @simplexml_load_string($body);
if ($xml && isset($xml->channel->item)) {
    echo "Items found: " . count($xml->channel->item) . "\n";
    $count = 0;
    foreach ($xml->channel->item as $item) {
        if ($count >= 5) break;
        $title = (string)$item->title;
        $googleLink = (string)$item->link;
        
        // Fetch original article page to get og:image
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $googleLink);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch2);
        
        $ogImage = null;
        if ($html && preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            $ogImage = $m[1];
        } elseif ($html && preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i', $html, $m)) {
            $ogImage = $m[1];
        }
        
        echo ($count + 1) . ": " . $title . "\n";
        echo "   OG Image: " . ($ogImage ?: "NOT FOUND") . "\n\n";
        $count++;
    }
}
