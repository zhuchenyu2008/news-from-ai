<?php
// 定时任务入口
require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/news_fetcher.php';
require_once __DIR__ . '/src/rss_fetcher.php';
require_once __DIR__ . '/src/ai_client.php';

$config = require __DIR__ . '/config.php';
$pdo = $pdo ?? null;

// 获取新闻
$newsList = fetch_news();

// 获取RSS
$rssList = fetch_rss_articles();

$promptSearch = $config['prompts']['search'];
$promptComment = $config['prompts']['comment'];
$promptSummary = $config['prompts']['summary'];

foreach ($newsList as $news) {
    $content = $news['title'] . "\n" . $news['snippet'] . "\n" . $news['link'];

    // 调用评论AI
    $comment = call_openai($config['openai']['comment'], $promptComment . "\n" . $content);

    // 调用整理汇总AI
    $summary = call_openai($config['openai']['summary'], $promptSummary . "\n" . $content);

    if ($summary) {
        $stmt = $pdo->prepare("INSERT INTO news (title, content_html, commentary, source_url, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$news['title'], $summary, $comment, $news['link']]);
    }
}

foreach ($rssList as $item) {
    $content = $item['title'] . "\n" . $item['description'] . "\n" . $item['link'];

    $comment = call_openai($config['openai']['comment'], $promptComment . "\n" . $content);
    $summary = call_openai($config['openai']['summary'], $promptSummary . "\n" . $content);

    if ($summary) {
        $stmt = $pdo->prepare("INSERT INTO news (title, content_html, commentary, source_url, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$item['title'], $summary, $comment, $item['link']]);
    }
}
