<?php
// news-from-ai - 定时任务执行脚本

// 设置时区，确保日志和时间戳正确
date_default_timezone_set('Asia/Shanghai'); // 或者您希望的任何时区

// 增加执行时间和内存限制，因为AI和RSS处理可能耗时较长
ini_set('max_execution_time', '300'); // 5分钟
ini_set('memory_limit', '256M'); // 256MB

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/services/AIService.php';
require_once __DIR__ . '/services/SearchService.php';
require_once __DIR__ . '/services/NewsProcessorService.php';

// --- 初始化 ---
try {
    $config = load_config();
    log_message('info', "定时任务开始执行...");

    $aiService = new AIService();

    // SearchService是可选的，检查配置是否完整
    $searchService = null;
    if (!empty($config['google_search']['api_key']) && !empty($config['google_search']['cse_id']) &&
        $config['google_search']['api_key'] !== 'YOUR_GOOGLE_SEARCH_API_KEY' &&
        $config['google_search']['cse_id'] !== 'YOUR_GOOGLE_CUSTOM_SEARCH_ENGINE_ID') {
        $searchService = new SearchService();
        log_message('info', "SearchService已初始化。");
    } else {
        log_message('info', "SearchService未初始化，因为API Key或CSE ID未配置或使用的是占位符。AI主动搜索功能将不可用。");
    }

    $newsProcessor = new NewsProcessorService($aiService, $searchService);

} catch (Exception $e) {
    // 捕获在初始化阶段（如加载配置、数据库连接）可能发生的异常
    log_message('error', "定时任务初始化失败: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    // 可以在这里添加邮件通知等关键错误报警机制
    exit(1); // 退出并返回错误码
}


// --- 主要处理逻辑 ---
$startTime = microtime(true);

try {
    // 1. 处理RSS源
    log_message('info', "开始处理RSS源...");
    $newsProcessor->processRssFeeds();
    log_message('info', "RSS源处理完毕。");

    // 2. AI主动搜寻新闻 (如果SearchService可用)
    if ($searchService) {
        log_message('info', "开始AI主动新闻搜寻...");
        $newsProcessor->sourceNewsWithAI();
        log_message('info', "AI主动新闻搜寻完毕。");
    } else {
        log_message('info', "跳过AI主动新闻搜寻，因为SearchService未配置或初始化失败。");
    }

} catch (Exception $e) {
    log_message('error', "定时任务执行过程中发生错误: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
    // 可以在这里添加邮件通知
} finally {
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    log_message('info', "定时任务执行完毕。总耗时: {$executionTime} 秒。");
}

exit(0); // 正常退出
?>
