<?php
// cron/fetch_and_process_news.php
// 定时任务主脚本

// 设置脚本最大执行时间，0为不限制（谨慎使用，确保AI调用有超时）
set_time_limit(0);
// 增加内存限制，AI处理和大量文本操作可能需要更多内存
ini_set('memory_limit', '512M'); // 增加到512M以防AI返回内容过大

// Set internal encoding to UTF-8 for mbstring functions
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

// --- 1. 加载核心文件 ---
$baseDir = __DIR__ . '/..';
require_once $baseDir . '/config/config.php';
require_once $baseDir . '/core/logger.php';
require_once $baseDir . '/core/ai_handler.php';
require_once $baseDir . '/core/rss_fetcher.php';
require_once $baseDir . '/core/db_handler.php';

log_info("定时任务开始: 获取和处理新闻。");

// --- 2. 获取数据库连接 ---
$db = get_db_connection();
if (!$db) {
    log_error("无法连接到数据库。任务终止。");
    exit(1); // 非正常退出
}

// --- 辅助函数：处理单条新闻的AI加工和存储 ---
/**
 * 处理单条新闻项目（来自AI搜索或RSS），进行AI评论、格式化，并存入数据库
 * @param PDO $db_conn 数据库连接
 * @param array $newsItem 包含新闻基本信息的数组
 * @return bool 成功处理并存储返回 true, 否则 false
 */
function process_and_store_news_item(PDO $db_conn, array $newsItem): bool {
    log_info("开始处理新闻条目: '" . $newsItem['title'] . "' (来源: " . $newsItem['source_url'] . ")");

    // 基本校验
    if (empty($newsItem['title']) || empty($newsItem['source_url'])) {
        log_warning("新闻条目缺少标题或来源URL，跳过。");
        return false;
    }
     // 确保 raw_data_for_ai 存在且不为空，否则后续AI调用可能无意义
    if (empty(trim($newsItem['raw_data_for_ai']))) {
        log_warning("新闻条目的 'raw_data_for_ai' 为空，无法进行AI处理，跳过: " . $newsItem['title']);
        // 或者可以尝试用标题作为 raw_data_for_ai
        // $newsItem['raw_data_for_ai'] = $newsItem['title'];
        return false;
    }


    // 检查新闻是否已存在
    if (news_exists($db_conn, $newsItem['source_url'])) {
        log_info("新闻已存在于数据库中，跳过: " . $newsItem['source_url']);
        return true; // 认为已处理
    }

    // 2a. AI评论
    $aiComment = null;
    if (defined('NEWS_COMMENT_AI_API_URL') && !empty(NEWS_COMMENT_AI_API_URL) && defined('NEWS_COMMENT_AI_API_KEY') && !empty(NEWS_COMMENT_AI_API_KEY)) {
        $commentPromptPlaceholders = [
            'news_title' => $newsItem['title'],
            'news_summary' => $newsItem['raw_data_for_ai']
        ];
        $commentPrompt = fill_prompt_placeholders(NEWS_COMMENT_AI_PROMPT, $commentPromptPlaceholders);

        // log_debug("新闻评论AI提示词: " . $commentPrompt); // Prompt可能很长
        $aiComment = call_openai_api(
            NEWS_COMMENT_AI_API_URL,
            NEWS_COMMENT_AI_API_KEY,
            NEWS_COMMENT_AI_MODEL,
            $commentPrompt
        );
        if ($aiComment) {
            log_info("AI评论获取成功 for '{$newsItem['title']}'.");
        } else {
            log_warning("未能获取AI评论 for '{$newsItem['title']}'.");
        }
    } else {
        log_info("新闻评论AI未配置或配置不完整，跳过评论生成。");
    }
    $newsItem['ai_comment'] = $aiComment;

    // 2b. AI整理汇总成HTML
    $summaryContent = $newsItem['summary'] ?? $newsItem['raw_data_for_ai'] ?? '无摘要';
    $defaultSummaryHtml = "<p><strong>摘要：</strong></p><p>" . nl2br(htmlspecialchars($summaryContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . "</p>";
    if ($aiComment) {
        $defaultSummaryHtml .= "<p><strong>AI评论：</strong></p><p>" . nl2br(htmlspecialchars($aiComment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . "</p>";
    }
    $formattedHtmlContent = "<p>AI未能成功生成HTML内容。</p>" . $defaultSummaryHtml;


    if (defined('NEWS_FORMAT_AI_API_URL') && !empty(NEWS_FORMAT_AI_API_URL) && defined('NEWS_FORMAT_AI_API_KEY') && !empty(NEWS_FORMAT_AI_API_KEY)) {
        $formatPromptPlaceholders = [
            'news_title' => $newsItem['title'],
            'ai_comment' => $aiComment ?? "无评论",
            'news_summary' => $newsItem['raw_data_for_ai'],
            'source_url' => $newsItem['source_url']
        ];
        $formatPrompt = fill_prompt_placeholders(NEWS_FORMAT_AI_PROMPT, $formatPromptPlaceholders);

        // log_debug("新闻整理汇总AI提示词: " . $formatPrompt); // Prompt可能很长
        $htmlOutput = call_openai_api(
            NEWS_FORMAT_AI_API_URL,
            NEWS_FORMAT_AI_API_KEY,
            NEWS_FORMAT_AI_MODEL, // 使用配置中指定的模型
            $formatPrompt,
            null,
            0.5,
            defined('NEWS_FORMAT_AI_MAX_TOKENS') ? (int)NEWS_FORMAT_AI_MAX_TOKENS : 3000
        );

        if ($htmlOutput) {
            log_info("AI内容排版成功 for '{$newsItem['title']}'.");
            $formattedHtmlContent = $htmlOutput;
        } else {
            log_warning("未能获取AI排版的HTML内容 for '{$newsItem['title']}'. 将使用默认内容。");
            // formattedHtmlContent 已经有默认值了
        }
    } else {
        log_info("新闻整理汇总AI未配置或配置不完整，跳过HTML生成。将使用基于摘要的默认HTML。");
        $formattedHtmlContent = $defaultSummaryHtml; // 使用上面构建的默认摘要和评论HTML
    }
    $newsItem['content_html'] = $formattedHtmlContent;


    // 2c. 存入数据库
    $dbData = [
        'title' => $newsItem['title'],
        'content_html' => $newsItem['content_html'],
        'ai_comment' => $newsItem['ai_comment'],
        'source_url' => $newsItem['source_url'],
        'source_name' => $newsItem['source_name'] ?? parse_url($newsItem['source_url'], PHP_URL_HOST),
        'type' => $newsItem['type'],
        'raw_data' => $newsItem['original_raw_data'], // db_handler会处理json_encode
        'category' => $newsItem['category'] ?? null,
        'fetched_at' => $newsItem['fetched_at'] ?? date('Y-m-d H:i:s'),
    ];

    $insertId = insert_news($db_conn, $dbData);
    if ($insertId) {
        log_info("新闻 '{$newsItem['title']}' 成功存入数据库，ID: {$insertId}.");
        return true;
    } else {
        log_error("新闻 '{$newsItem['title']}' 存入数据库失败.");
        return false;
    }
}


// --- 3. AI生成新闻获取与处理 ---
log_info("===== 开始获取AI生成新闻 =====");
if (defined('NEWS_KEYWORDS') && is_array(NEWS_KEYWORDS) && !empty(NEWS_KEYWORDS) &&
    defined('GOOGLE_SEARCH_API_KEY') && !empty(GOOGLE_SEARCH_API_KEY) && defined('GOOGLE_SEARCH_CX') && !empty(GOOGLE_SEARCH_CX) &&
    defined('NEWS_FETCH_AI_API_URL') && !empty(NEWS_FETCH_AI_API_URL) && defined('NEWS_FETCH_AI_API_KEY') && !empty(NEWS_FETCH_AI_API_KEY)) {

    $keywordsToProcess = NEWS_KEYWORDS;
    // $keywordsLimit = defined('NEWS_KEYWORDS_PER_RUN') ? (int)NEWS_KEYWORDS_PER_RUN : count($keywordsToProcess);
    // shuffle($keywordsToProcess); // 每次随机打乱顺序
    // $keywordsToProcess = array_slice($keywordsToProcess, 0, $keywordsLimit);

    $consecutiveJsonDecodeFailures = 0;
    $maxConsecutiveJsonFailures = 3; // Max consecutive failures before pausing
    $pauseDurationSeconds = 10 * 60; // 10 minutes

    foreach ($keywordsToProcess as $keyword) {
        log_info("处理关键词: {$keyword}");

        // Check circuit breaker before processing keyword
        if ($consecutiveJsonDecodeFailures >= $maxConsecutiveJsonFailures) {
            log_warning("关键词处理熔断：连续 {$consecutiveJsonDecodeFailures} 次JSON解码失败。暂停 {$pauseDurationSeconds} 秒...");
            sleep($pauseDurationSeconds);
            $consecutiveJsonDecodeFailures = 0; // Reset counter after pause
            log_info("暂停结束，继续处理关键词。");
        }

        $searchNumResults = defined('MAX_AI_NEWS_PER_KEYWORD') ? ((int)MAX_AI_NEWS_PER_KEYWORD * 2 + 2) : 6; // 多获取一些给AI筛选,至少为2，最多10
        $searchNumResults = max(2, min(10, $searchNumResults));

        $searchResults = google_search(GOOGLE_SEARCH_API_KEY, GOOGLE_SEARCH_CX, $keyword, $searchNumResults);

        if ($searchResults === null) {
            log_warning("Google搜索API调用失败或配置错误 for keyword '{$keyword}'.");
            continue;
        }
        if (empty($searchResults)) {
            log_info("Google搜索未返回任何结果 for keyword '{$keyword}'.");
            continue;
        }

        $searchSummaryForAI = "";
        foreach($searchResults as $idx => $item){
            $searchSummaryForAI .= ($idx+1).". Title: ".($item['title'] ?? 'N/A')."\n   Link: ".($item['link'] ?? 'N/A')."\n   Snippet: ".($item['snippet'] ?? 'N/A')."\n\n";
        }

        $fetchPrompt = fill_prompt_placeholders(NEWS_FETCH_AI_PROMPT, ['search_results' => $searchSummaryForAI, 'target_keyword' => $keyword]);
        // log_debug("新闻获取AI提示词 for '{$keyword}': " . $fetchPrompt);

        $fetchedNewsJson = call_openai_api(
            NEWS_FETCH_AI_API_URL,
            NEWS_FETCH_AI_API_KEY,
            NEWS_FETCH_AI_MODEL,
            $fetchPrompt,
            "Strictly output content that conforms to JSON schema. Do not include any other characters, comments, or Markdown formatting." // System Prompt
        );

        if (!$fetchedNewsJson) {
            log_warning("新闻获取AI未能返回数据 for keyword '{$keyword}'.");
            continue;
        }

        $fetchedNewsList = json_decode($fetchedNewsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($fetchedNewsList)) {
            // Use mb_substr for UTF-8 safe substring for logging
            log_error("新闻获取AI返回的JSON无效 for keyword '{$keyword}'. Response (first 200 chars): " . mb_substr($fetchedNewsJson, 0, 200, 'UTF-8'));
            // The detailed logging of the full raw response is now handled by core/ai_handler.php
            $consecutiveJsonDecodeFailures++;
            continue;
        }
        // If JSON decoding was successful, reset the failure counter
        $consecutiveJsonDecodeFailures = 0;

        // Check the structure of the decoded JSON
        if (isset($fetchedNewsList['news']) && is_array($fetchedNewsList['news'])) {
            $fetchedNewsList = $fetchedNewsList['news']; // Common pattern: {"news": [...]}
        } elseif (empty($fetchedNewsList) || !isset($fetchedNewsList[0]['title'])) {
             // This case handles if the response is a direct list OR if it's malformed in structure
             // (e.g., not an array of objects with a 'title' key)
             log_warning("新闻获取AI返回的数据格式不符合预期 for keyword '{$keyword}'. Expected an array of news items (possibly under a 'news' key), each with a 'title'. Response (first 200 chars): " . mb_substr($fetchedNewsJson, 0, 200, 'UTF-8'));
             // This is a content structure error, not strictly a JSON syntax error.
             // We might not want to trigger the circuit breaker for this, or we might.
             // For now, we'll continue to the next keyword without incrementing $consecutiveJsonDecodeFailures,
             // as the JSON itself was valid. If this becomes a frequent issue, it might indicate a prompt problem.
             continue;
        }

        log_info("新闻获取AI为 '{$keyword}' 返回了 " . count($fetchedNewsList) . " 条初步新闻。");
        $aiNewsCounter = 0;
        $maxAiNews = defined('MAX_AI_NEWS_PER_KEYWORD') ? (int)MAX_AI_NEWS_PER_KEYWORD : 3;

        foreach ($fetchedNewsList as $aiNewsItem) {
            if ($aiNewsCounter >= $maxAiNews ) break;

            if (empty($aiNewsItem['title']) || empty($aiNewsItem['source_url']) || empty($aiNewsItem['summary'])) {
                log_warning("AI获取的新闻条目缺少必要字段，跳过。Data: " . json_encode($aiNewsItem, JSON_UNESCAPED_UNICODE));
                continue;
            }

            $newsItemToProcess = [
                'title' => $aiNewsItem['title'],
                'summary' => $aiNewsItem['summary'],
                'source_url' => $aiNewsItem['source_url'],
                'source_name' => $aiNewsItem['source_name'] ?? parse_url($aiNewsItem['source_url'], PHP_URL_HOST),
                'type' => 'ai_generated',
                'category' => $keyword,
                'fetched_at' => date('Y-m-d H:i:s'),
                'raw_data_for_ai' => $aiNewsItem['summary'],
                'original_raw_data' => $aiNewsItem
            ];
            if(process_and_store_news_item($db, $newsItemToProcess)) {
                $aiNewsCounter++;
            }
        }
    }
} else {
    log_info("AI生成新闻的相关配置不完整，跳过此部分。");
}
log_info("===== AI生成新闻获取处理完成 =====");


// --- 4. RSS新闻获取与处理 ---
log_info("===== 开始获取RSS新闻 =====");
if (defined('RSS_SOURCES') && is_array(RSS_SOURCES) && !empty(RSS_SOURCES)) {
    foreach (RSS_SOURCES as $sourceConfig) {
        if (empty($sourceConfig['url'])) {
            log_warning("RSS源配置中URL为空，跳过。");
            continue;
        }

        $rssUrl = $sourceConfig['url'];
        $maxItemsPerFeed = $sourceConfig['fetch_count'] ?? (defined('MAX_RSS_ITEMS_TO_PROCESS_PER_SOURCE') ? (int)MAX_RSS_ITEMS_TO_PROCESS_PER_SOURCE : 5);
        $rssCategory = $sourceConfig['category'] ?? null;
        $sourceName = $sourceConfig['name'] ?? parse_url($rssUrl, PHP_URL_HOST);

        log_info("处理RSS源: {$rssUrl} (分类: {$rssCategory}, 名称: {$sourceName})");
        $rssItems = fetch_rss_items($rssUrl, $maxItemsPerFeed, $rssCategory);

        if (empty($rssItems)) {
            log_info("未能从RSS源 {$rssUrl} 获取任何新条目或所有条目已处理。");
            continue;
        }

        foreach ($rssItems as $rssItem) {
            if (empty($rssItem['title']) || empty($rssItem['link'])) {
                log_warning("RSS条目缺少标题或链接，跳过。Source: {$rssUrl}, GUID: {$rssItem['guid']}");
                continue;
            }

            $textSummaryForAI = strip_tags(html_entity_decode($rssItem['description']));
            if (mb_strlen($textSummaryForAI) > 1500) {
                $textSummaryForAI = mb_substr($textSummaryForAI, 0, 1497) . "...";
            }
            if (empty(trim($textSummaryForAI))) {
                $textSummaryForAI = $rssItem['title'];
            }

            if (defined('RSS_SUMMARY_AI_API_URL') && !empty(RSS_SUMMARY_AI_API_URL) && !empty(trim($rssItem['description'])) && defined('RSS_SUMMARY_AI_API_KEY') && !empty(RSS_SUMMARY_AI_API_KEY)) {
                $summaryPrompt = fill_prompt_placeholders(RSS_SUMMARY_AI_PROMPT, ['article_content' => $textSummaryForAI]);
                // log_debug("RSS摘要AI提示词 for '{$rssItem['title']}': " . $summaryPrompt);
                $aiRssSummary = call_openai_api(
                    RSS_SUMMARY_AI_API_URL,
                    RSS_SUMMARY_AI_API_KEY,
                    RSS_SUMMARY_AI_MODEL,
                    $summaryPrompt
                );
                if ($aiRssSummary) {
                    log_info("AI对RSS文章 '{$rssItem['title']}' 摘要成功。");
                    $textSummaryForAI = $aiRssSummary;
                    $rssItem['ai_summary'] = $aiRssSummary;
                } else {
                    log_warning("未能为RSS文章 '{$rssItem['title']}' 获取AI摘要，将使用原始内容处理后的摘要。");
                }
            }

            $newsItemToProcess = [
                'title' => $rssItem['title'],
                'summary' => $rssItem['description'],
                'source_url' => $rssItem['link'],
                'source_name' => $sourceName,
                'type' => 'rss',
                'category' => $rssItem['category'] ?? $rssCategory,
                'fetched_at' => $rssItem['pubDate'],
                'raw_data_for_ai' => $textSummaryForAI,
                'original_raw_data' => $rssItem
            ];
            process_and_store_news_item($db, $newsItemToProcess);
        }
    }
} else {
    log_info("RSS源未配置，跳过此部分。");
}
log_info("===== RSS新闻获取处理完成 =====");

log_info("定时任务结束。");
exit(0); // 正常退出
?>
