<?php
// RSS抓取逻辑
require_once __DIR__ . '/logger.php';

$config = require __DIR__ . '/../config.php';

function fetch_rss_articles(): array {
    global $config;
    $feeds = $config['rss_feeds'];
    $limit = $config['rss_article_limit'];
    $articles = [];

    foreach ($feeds as $feedUrl) {
        log_message("获取RSS: $feedUrl");
        $content = file_get_contents($feedUrl);
        if ($content === false) {
            log_message('获取RSS失败: ' . $feedUrl);
            continue;
        }
        $xml = simplexml_load_string($content);
        if (!$xml) {
            log_message('解析RSS失败: ' . $feedUrl);
            continue;
        }
        $count = 0;
        foreach ($xml->channel->item as $item) {
            $articles[] = [
                'title' => (string)$item->title,
                'link' => (string)$item->link,
                'description' => (string)$item->description
            ];
            $count++;
            if ($count >= $limit) break;
        }
    }
    return $articles;
}
