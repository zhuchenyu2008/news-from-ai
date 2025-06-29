<?php
// core/rss_fetcher.php

// 确保配置文件已加载 (主要为了RSS_SOURCES, 如果有其他相关配置)
if (!defined('DB_HOST')) { // 检查一个config.php中应存在的常量
    $configPath = __DIR__ . '/../config/config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        error_log("rss_fetcher.php: 配置文件 config.php 未找到。");
        // 在logger可用前，先用error_log
        if (function_exists('log_error')) {
            log_error("rss_fetcher.php: 配置文件 config.php 未找到。");
        }
        die("错误：RSS模块配置文件缺失。");
    }
}

// 确保日志模块已加载
$loggerPath = __DIR__ . '/logger.php';
if (file_exists($loggerPath)) {
    require_once $loggerPath;
} else {
    error_log("rss_fetcher.php: 日志模块 logger.php 未找到。");
    if (!function_exists('log_error')) { // Basic fallback logger
        function log_info($msg){ error_log("INFO: ".$msg); }
        function log_warning($msg){ error_log("WARNING: ".$msg); }
        function log_error($msg){ error_log("ERROR: ".$msg); }
        function log_debug($msg){ error_log("DEBUG: ".$msg); }
    }
}

/**
 * 从指定的RSS源URL获取最新的文章条目
 *
 * @param string $rssUrl RSS源的URL
 * @param int $maxItems 获取的最大条目数量
 * @param string|null $category RSS源的预设分类 (可选)
 * @return array 包含文章信息的数组，每篇文章是一个包含 'title', 'link', 'pubDate_str', 'pubDate', 'description', 'guid', 'raw_item_xml', 'category', 'source_rss_url' 的关联数组。失败时返回空数组。
 */
function fetch_rss_items(string $rssUrl, int $maxItems = 5, ?string $category = null): array {
    log_info("开始获取RSS源: {$rssUrl} (最多 {$maxItems} 条)");
    $fetchedItems = [];

    if (empty($rssUrl) || !filter_var($rssUrl, FILTER_VALIDATE_URL)) {
        log_error("无效的RSS源URL: {$rssUrl}");
        return $fetchedItems;
    }

    // 使用cURL获取RSS内容，以便更好地控制超时和错误处理
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $rssUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // RSS源的超时时间增加到30秒
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 跟随重定向
    curl_setopt($ch, CURLOPT_USERAGENT, 'NewsFromAI_RSS_Fetcher/1.0 (PHP cURL)'); // 设置User-Agent
    // 对于某些SSL证书可能有问题的源，可以临时禁用严格校验，但不推荐生产环境长期使用
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $xmlContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        log_error("获取RSS源 {$rssUrl} cURL错误: " . $curlError);
        return $fetchedItems;
    }

    if ($httpCode !== 200) {
        log_error("获取RSS源 {$rssUrl} HTTP错误: Code={$httpCode}");
        // log_debug("RSS源 {$rssUrl} 返回内容: " . $xmlContent); // 调试时可以取消注释
        return $fetchedItems;
    }

    if (empty($xmlContent)) {
        log_warning("RSS源 {$rssUrl} 返回内容为空。");
        return $fetchedItems;
    }

    // 禁止XML外部实体加载，防止XXE攻击
    libxml_disable_entity_loader(true);
    // 捕获SimpleXML的解析错误
    $oldUseInternalErrors = libxml_use_internal_errors(true);

    $xml = simplexml_load_string($xmlContent);

    if ($xml === false) {
        $errors = libxml_get_errors();
        $errorMsg = "解析RSS XML失败: {$rssUrl}.";
        foreach ($errors as $error) {
            $errorMsg .= "\n - " . trim($error->message) . " (line: {$error->line}, column: {$error->column})";
        }
        log_error($errorMsg);
        libxml_clear_errors();
        libxml_use_internal_errors($oldUseInternalErrors);
        return $fetchedItems;
    }
    libxml_clear_errors(); // 清除累积的错误
    libxml_use_internal_errors($oldUseInternalErrors);

    // 检查是RSS feed (channel->item) 还是 Atom feed (entry)
    $items = null;
    if (isset($xml->channel->item)) {
        $items = $xml->channel->item;
    } elseif (isset($xml->entry)) {
        $items = $xml->entry;
    } else {
        log_warning("RSS源 {$rssUrl} 中未找到 <item> 或 <entry> 标签。可能是无效的RSS/Atom格式。");
        return $fetchedItems;
    }

    $count = 0;

    foreach ($items as $item) {
        if ($count >= $maxItems) {
            break;
        }

        $title = $item->title ? (string)$item->title : 'N/A';

        $link = 'N/A';
        if (isset($item->link)) {
            if (is_string($item->link) && !empty(trim((string)$item->link))) {
                $link = (string)$item->link;
            } elseif (isset($item->link['href'])) {
                $link = (string)$item->link['href'];
            } elseif (is_array($item->link) && count($item->link) > 0) {
                 foreach($item->link as $link_node){
                    if (isset($link_node['rel']) && $link_node['rel'] == 'alternate' && isset($link_node['href'])) {
                        $link = (string)$link_node['href'];
                        break;
                    } elseif (isset($link_node['href'])) { // Fallback to first href if no alternate
                        $link = (string)$link_node['href'];
                    }
                 }
                 if ($link === 'N/A' && isset($item->link[0]['href'])) $link = (string)$item->link[0]['href']; // Absolute fallback
            }
        }
        // 有些RSS <link> 标签可能在CDATA中，SimpleXML默认处理
        // 对于一些feed，链接可能在 guid 且 isPermaLink="true"
        if ($link === 'N/A' && isset($item->guid) && isset($item->guid['isPermaLink']) && (string)$item->guid['isPermaLink'] == 'true') {
            $link = (string)$item->guid;
        }


        $pubDateStr = '';
        if (isset($item->pubDate)) {
            $pubDateStr = (string)$item->pubDate;
        } elseif (isset($item->published)) {
            $pubDateStr = (string)$item->published;
        } elseif (isset($item->updated)) {
            $pubDateStr = (string)$item->updated;
        } elseif ($item->children('dc', true)->date) { // Dublin Core namespace
            $pubDateStr = (string)$item->children('dc', true)->date;
        }

        $pubTimestamp = $pubDateStr ? @strtotime($pubDateStr) : time(); // Use @ to suppress errors on invalid date formats
        if ($pubTimestamp === false) $pubTimestamp = time(); // Fallback if strtotime fails
        $formattedPubDate = date('Y-m-d H:i:s', $pubTimestamp);

        $description = '';
        if (isset($item->description)) {
            $description = (string)$item->description;
        } elseif (isset($item->summary)) {
            $description = (string)$item->summary;
        } elseif (isset($item->content) && is_string($item->content)) {
             $description = (string)$item->content;
        } elseif ($item->children('content', true)->encoded) { // <content:encoded>
            $description = (string)$item->children('content', true)->encoded;
        }
        // 移除CDATA标记，如果存在的话 (SimpleXML通常会自动处理)
        $description = preg_replace('/<!\[CDATA\[(.*?)\]\]>/s', '$1', $description);


        $guid = '';
        if (isset($item->guid)) {
            $guid = (string)$item->guid;
        } elseif (isset($item->id)) {
            $guid = (string)$item->id;
        }
        if (empty(trim($guid))) {
            $guid = $link; // Fallback to link if guid is empty or whitespace
        }

        $raw_item_xml = $item->asXML();
        if ($raw_item_xml === false) $raw_item_xml = "Failed to get XML for item.";


        $fetchedItems[] = [
            'title' => trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8')), // Get clean title
            'link' => trim($link),
            'pubDate_str' => $pubDateStr,
            'pubDate' => $formattedPubDate,
            'description' => trim($description), // Keep HTML for AI to process, or strip later
            'guid' => trim($guid),
            'category' => $category,
            'source_rss_url' => $rssUrl,
            'raw_item_xml' => $raw_item_xml
        ];
        $count++;
    }

    if (count($fetchedItems) > 0) {
        log_info("从 {$rssUrl} 成功获取 " . count($fetchedItems) . " 条新闻。");
    } else {
        log_warning("从 {$rssUrl} 未能获取到有效新闻条目。");
    }
    return $fetchedItems;
}


// 用法示例 (确保 config.php 和 logger.php 已加载):
/*
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/ai_handler.php'; // For fill_prompt_placeholders and call_openai_api if testing summary

if (defined('RSS_SOURCES') && is_array(RSS_SOURCES)) {
    foreach (RSS_SOURCES as $source_config) {
        if (isset($source_config['url']) && isset($source_config['fetch_count'])) {
            log_info("正在处理RSS源: " . $source_config['url']);
            $items = fetch_rss_items($source_config['url'], $source_config['fetch_count'], $source_config['category'] ?? null);
            if (!empty($items)) {
                foreach ($items as $item_data) {
                    log_debug("--- RSS 条目 ---");
                    log_debug("标题: " . $item_data['title']);
                    log_debug("链接: " . $item_data['link']);
                    log_debug("发布日期: " . $item_data['pubDate'] . " (原始: ".$item_data['pubDate_str'].")");
                    log_debug("GUID: " . $item_data['guid']);
                    log_debug("分类: " . $item_data['category']);
                    $clean_description = strip_tags(html_entity_decode($item_data['description']));
                    // log_debug("描述 (前100字符): " . mb_substr($clean_description, 0, 100));
                    // log_debug("原始XML片段: " . $item_data['raw_item_xml']);
                    log_debug("------------------");

                    // // 示例：调用AI进行摘要 (确保相关AI配置已在config.php中定义)
                    // if (defined('RSS_SUMMARY_AI_PROMPT') && !empty(RSS_SUMMARY_AI_PROMPT)) {
                    //     $summaryPrompt = fill_prompt_placeholders(RSS_SUMMARY_AI_PROMPT, ['article_content' => $clean_description]);
                    //     $aiSummary = call_openai_api(
                    //         RSS_SUMMARY_AI_API_URL,
                    //         RSS_SUMMARY_AI_API_KEY,
                    //         RSS_SUMMARY_AI_MODEL,
                    //         $summaryPrompt
                    //     );
                    //     if ($aiSummary) {
                    //        log_info("AI对RSS文章 '{$item_data['title']}' 的摘要: " . $aiSummary);
                    //        // $item_data['ai_summary'] = $aiSummary; // 可以添加到item数组中或直接处理
                    //     } else {
                    //        log_warning("未能为RSS文章 '{$item_data['title']}' 获取AI摘要。");
                    //     }
                    // }
                }
            } else {
                log_warning("未能从 " . $source_config['url'] . " 获取任何条目。");
            }
        }
    }
} else {
    log_error("配置文件中未定义或RSS_SOURCES格式不正确。");
}
*/

?>
