<?php
// news-from-ai/public/cron_runner.php
// 定时任务执行入口

// 确保此脚本只能从CLI运行
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Access Denied. This script can only be run from the command line.");
}

// 设置一个较长的执行时间，因为AI交互可能耗时
ini_set('max_execution_time', 0); // 0表示不限制
ini_set('memory_limit', '512M'); // 根据需要调整内存限制

$app = require_once __DIR__ . '/../src/bootstrap.php';

/** @var NewsFromAI\Config $config */
$config = $app['config'];
/** @var NewsFromAI\Logger $loggerType */ // Logger是静态类
$loggerType = $app['logger'];
/** @var NewsFromAI\Database $dbType */ // Database是静态类
$dbType = $app['db'];
/** @var NewsFromAI\NewsProcessingService $newsProcessingService */
$newsProcessingService = $app['newsProcessingService'];

// --- 辅助函数：记录Cron Job日志到数据库 ---
function logCronJobStatus(string $jobType, string $status, string $message = '', int $itemsProcessed = 0, ?int $logId = null): ?int {
    global $dbType, $loggerType;
    if (!$dbType::getConnection()) {
        $loggerType::warning("CronRunner: Database connection not available, cannot log cron job to DB.", ['job_type' => $jobType, 'status' => $status]);
        return null;
    }

    if ($logId) {
        // 更新现有记录
        $sql = "UPDATE cron_job_logs SET end_time = NOW(), status = ?, messages = CONCAT(COALESCE(messages, ''), CHAR(10 USING utf8mb4), ?), items_processed = ? WHERE id = ?";
        $params = [$status, $message, $itemsProcessed, $logId];
    } else {
        // 插入新记录
        $sql = "INSERT INTO cron_job_logs (job_type, start_time, status, messages, items_processed) VALUES (?, NOW(), ?, ?, ?)";
        $params = [$jobType, $status, $message, $itemsProcessed];
    }

    try {
        $dbType::execute($sql, $params);
        return $logId ?: (int)$dbType::lastInsertId();
    } catch (Throwable $e) {
        $loggerType::error("CronRunner: Failed to log cron job status to database.", ['job_type' => $jobType, 'error' => $e->getMessage()]);
        return $logId; // 返回传入的ID或null
    }
}

// --- 任务定义 ---
$taskType = $argv[1] ?? 'all'; // 从命令行参数获取任务类型，默认为 'all'

$loggerType::info("CronRunner: Starting task '{$taskType}'.");

$mainJobLogId = logCronJobStatus('cron_runner_main', 'running', "Task '{$taskType}' started.");
$totalItemsProcessed = 0;
$errorsOccurred = false;

// --- 任务1: 获取和处理AI搜索的新闻 ---
if ($taskType === 'fetch_ai_news' || $taskType === 'all') {
    $aiJobLogId = null;
    try {
        $loggerType::info("CronRunner: Starting AI news fetching task.");
        $aiJobLogId = logCronJobStatus('ai_news_fetch', 'running', 'AI news fetching started.');

        $keywordsConfig = $config->get('news_sources.keywords');
        if (empty($keywordsConfig)) {
            $loggerType::warning("CronRunner: No keywords configured for AI news fetching in config.ini (news_sources.keywords).");
            logCronJobStatus('ai_news_fetch', 'warning', 'No keywords configured.', 0, $aiJobLogId);
        } else {
            $keywordsList = explode(',', $keywordsConfig);
            $itemsProcessedThisRun = 0;
            foreach ($keywordsList as $keyword) {
                $keyword = trim($keyword);
                if (empty($keyword)) continue;

                $loggerType::info("CronRunner: Processing AI news for keyword: '{$keyword}'");
                $count = $newsProcessingService->processAISearchNews($keyword);
                $itemsProcessedThisRun += $count;
                $loggerType::info("CronRunner: Processed {$count} AI news items for keyword '{$keyword}'.");
                 if ($count > 0) {
                     logCronJobStatus('ai_news_fetch', 'running', "Processed {$count} items for keyword '{$keyword}'.", $itemsProcessedThisRun, $aiJobLogId); // Update progress
                 }
            }
            $totalItemsProcessed += $itemsProcessedThisRun;
            logCronJobStatus('ai_news_fetch', 'success', "AI news fetching completed. Processed {$itemsProcessedThisRun} items in total.", $itemsProcessedThisRun, $aiJobLogId);
            $loggerType::info("CronRunner: AI news fetching task completed. Processed {$itemsProcessedThisRun} items.");
        }
    } catch (Throwable $e) {
        $errorsOccurred = true;
        $errorMessage = "Error during AI news fetching: " . $e->getMessage();
        $loggerType::critical("CronRunner: " . $errorMessage, ['trace' => $e->getTraceAsString()]);
        if ($aiJobLogId) {
            logCronJobStatus('ai_news_fetch', 'failed', $errorMessage, $totalItemsProcessed, $aiJobLogId);
        } else { // 如果在logCronJobStatus之前就出错了
             logCronJobStatus('ai_news_fetch', 'failed', $errorMessage, $totalItemsProcessed);
        }
    }
}

// --- 任务2: 获取和处理RSS新闻 ---
if ($taskType === 'fetch_rss_news' || $taskType === 'all') {
    $rssJobLogId = null;
    try {
        $loggerType::info("CronRunner: Starting RSS news fetching task.");
        $rssJobLogId = logCronJobStatus('rss_news_fetch', 'running', 'RSS news fetching started.');

        $rssUrlsConfig = $config->get('rss.rss_urls');
        $rssEnabled = $config->get('rss.enabled', true); // 假设默认启用

        if (!$rssEnabled) {
            $loggerType::info("CronRunner: RSS news fetching is disabled in config.ini.");
            logCronJobStatus('rss_news_fetch', 'skipped', 'RSS fetching disabled in config.', 0, $rssJobLogId);
        } elseif (empty($rssUrlsConfig) || !is_array($rssUrlsConfig)) {
            $loggerType::warning("CronRunner: No RSS URLs configured or invalid format in config.ini (rss.rss_urls).");
            logCronJobStatus('rss_news_fetch', 'warning', 'No RSS URLs configured.', 0, $rssJobLogId);
        } else {
            // 获取所有启用的RSS feeds从数据库
            $feedsToFetch = $dbType::fetchAll("SELECT id, url, name FROM rss_feeds WHERE is_enabled = TRUE");
            if ($feedsToFetch === false) {
                 $loggerType->error("CronRunner: Could not fetch RSS feed list from database.");
                 logCronJobStatus('rss_news_fetch', 'failed', 'Could not fetch RSS feed list from DB.', 0, $rssJobLogId);
            } elseif (empty($feedsToFetch)) {
                 $loggerType::info("CronRunner: No enabled RSS feeds found in the database.");
                 logCronJobStatus('rss_news_fetch', 'success', 'No enabled RSS feeds in DB.', 0, $rssJobLogId);
            } else {
                $itemsProcessedThisRun = 0;
                $loggerType::info("CronRunner: Found " . count($feedsToFetch) . " RSS feeds to process from database.");
                foreach ($feedsToFetch as $feed) {
                    $loggerType::info("CronRunner: Processing RSS feed: '{$feed['name']}' ({$feed['url']})");
                    $count = $newsProcessingService->processRSSFeedNews((int)$feed['id'], $feed['url'], $feed['name']);
                    $itemsProcessedThisRun += $count;
                    $loggerType->info("CronRunner: Processed {$count} items for RSS feed '{$feed['name']}'.");
                     if ($count > 0) {
                        logCronJobStatus('rss_news_fetch', 'running', "Processed {$count} items for feed '{$feed['name']}'.", $itemsProcessedThisRun, $rssJobLogId);
                    }
                }
                $totalItemsProcessed += $itemsProcessedThisRun;
                logCronJobStatus('rss_news_fetch', 'success', "RSS news fetching completed. Processed {$itemsProcessedThisRun} items in total.", $itemsProcessedThisRun, $rssJobLogId);
                $loggerType::info("CronRunner: RSS news fetching task completed. Processed {$itemsProcessedThisRun} items.");
            }
        }
    } catch (Throwable $e) {
        $errorsOccurred = true;
        $errorMessage = "Error during RSS news fetching: " . $e->getMessage();
        $loggerType::critical("CronRunner: " . $errorMessage, ['trace' => $e->getTraceAsString()]);
         if ($rssJobLogId) {
            logCronJobStatus('rss_news_fetch', 'failed', $errorMessage, $totalItemsProcessed - ($taskType === 'all' ? ($totalItemsProcessed - $itemsProcessedThisRun) : 0), $rssJobLogId);
        } else {
            logCronJobStatus('rss_news_fetch', 'failed', $errorMessage, $totalItemsProcessed - ($taskType === 'all' ? ($totalItemsProcessed - $itemsProcessedThisRun) : 0));
        }
    }
}


// --- 清理旧日志 (示例) ---
if ($taskType === 'cleanup_logs' || $taskType === 'all') {
    // 这是一个可选的维护任务，可以单独运行或作为 'all' 的一部分
    // 例如，删除超过30天的 cron_job_logs 和 ai_api_logs
    $cleanupJobLogId = null;
    try {
        $loggerType::info("CronRunner: Starting log cleanup task.");
        $cleanupJobLogId = logCronJobStatus('log_cleanup', 'running', 'Log cleanup started.');
        $daysToKeep = 30;
        $dateThreshold = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        $deletedCronLogs = $dbType::execute("DELETE FROM cron_job_logs WHERE start_time < ?", [$dateThreshold]);
        $deletedApiLogs = $dbType::execute("DELETE FROM ai_api_logs WHERE request_timestamp < ?", [$dateThreshold]);

        $cleanupMessage = "Log cleanup completed. Deleted cron logs: {$deletedCronLogs}. Deleted API logs: {$deletedApiLogs}. Kept logs from last {$daysToKeep} days.";
        logCronJobStatus('log_cleanup', 'success', $cleanupMessage, ($deletedCronLogs ?:0) + ($deletedApiLogs?:0), $cleanupJobLogId);
        $loggerType::info("CronRunner: " . $cleanupMessage);

    } catch (Throwable $e) {
        $errorsOccurred = true;
        $errorMessage = "Error during log cleanup: " . $e->getMessage();
        $loggerType::error("CronRunner: " . $errorMessage);
        if($cleanupJobLogId) {
            logCronJobStatus('log_cleanup', 'failed', $errorMessage, 0, $cleanupJobLogId);
        } else {
            logCronJobStatus('log_cleanup', 'failed', $errorMessage, 0);
        }
    }
}


if ($taskType === 'all' && !$errorsOccurred) {
    logCronJobStatus('cron_runner_main', 'success', "All tasks completed. Total items processed: {$totalItemsProcessed}.", $totalItemsProcessed, $mainJobLogId);
} elseif ($errorsOccurred) {
    logCronJobStatus('cron_runner_main', 'failed', "One or more tasks failed. Total items processed before failure: {$totalItemsProcessed}. Check specific task logs.", $totalItemsProcessed, $mainJobLogId);
} elseif ($taskType !== 'all') { // Specific task ran
     logCronJobStatus('cron_runner_main', 'success', "Task '{$taskType}' completed. Processed {$totalItemsProcessed} items.", $totalItemsProcessed, $mainJobLogId);
}


$loggerType::info("CronRunner: Task '{$taskType}' finished. Total items processed in this run: {$totalItemsProcessed}.");
echo "Cron task '{$taskType}' finished. Check logs for details.\n";
exit(0);

?>
