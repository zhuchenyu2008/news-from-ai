<?php
// 新闻抓取相关逻辑
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/ai_client.php';

$config = require __DIR__ . '/../config.php';

function fetch_news(): array {
    global $config;
    $searchConfig = $config['google_search'];
    $topics = $config['topics'];

    $results = [];
    foreach ($topics as $topic) {
        $query = urlencode($topic);
        $url = "https://www.googleapis.com/customsearch/v1?key={$searchConfig['api_key']}&cx={$searchConfig['cx']}&q={$query}";
        log_message("搜索新闻: $topic");
        $json = file_get_contents($url);
        if ($json === false) {
            log_message('搜索失败: ' . $topic);
            continue;
        }
        $data = json_decode($json, true);
        foreach ($data['items'] ?? [] as $item) {
            $results[] = [
                'title' => $item['title'],
                'link' => $item['link'],
                'snippet' => $item['snippet']
            ];
        }
    }
    return $results;
}
