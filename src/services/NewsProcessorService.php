<?php
// news-from-ai - 新闻处理服务类

require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/AIService.php';
require_once __DIR__ . '/SearchService.php';

// 简单的RSS解析器 (PicoFeed等库更好，但为了减少依赖，这里用一个非常基础的)
// 注意：这个基础解析器可能无法处理所有RSS feed的复杂性。
// 推荐使用成熟的库如 PicoFeed (composer require fguillot/picofeed)
class SimpleRssParser {
    public function parse(string $xmlContent): ?array {
        try {
            $xml = new SimpleXMLElement($xmlContent, LIBXML_NOCDATA | LIBXML_NOWARNING);
            if ($xml === false) return null;

            $items = [];
            // RSS 2.0
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $items[] = [
                        'title' => (string)$item->title,
                        'link' => (string)$item->link,
                        'description' => (string)($item->description ?? $item->encoded ?? ''), // Atom uses 'content', RSS 'description' or 'content:encoded'
                        'pubDate' => isset($item->pubDate) ? (string)$item->pubDate : (isset($item->updated) ? (string)$item->updated : null), // Atom uses 'updated'
                        'guid' => (string)($item->guid ?? $item->id ?? $item->link), // Atom uses 'id'
                    ];
                }
            }
            // Atom 1.0
            elseif (isset($xml->entry)) {
                 foreach ($xml->entry as $entry) {
                    $link = '';
                    if (isset($entry->link)) {
                        if (is_array($entry->link)) { // Atom can have multiple links
                            foreach($entry->link as $l) {
                                if (isset($l['rel']) && $l['rel'] == 'alternate') {
                                    $link = (string)$l['href'];
                                    break;
                                }
                            }
                            if (empty($link) && isset($entry->link[0]['href'])) { // fallback to first link
                                $link = (string)$entry->link[0]['href'];
                            }
                        } else {
                             $link = (string)$entry->link['href'];
                        }
                    }

                    $items[] = [
                        'title' => (string)$entry->title,
                        'link' => $link,
                        'description' => (string)($entry->summary ?? $entry->content ?? ''),
                        'pubDate' => (string)($entry->updated ?? $entry->published ?? null),
                        'guid' => (string)($entry->id ?? $link),
                    ];
                }
            }
            return $items;
        } catch (Exception $e) {
            log_message('error', 'RSS解析失败: ' . $e->getMessage(), ['xml_preview' => substr($xmlContent, 0, 500)]);
            return null;
        }
    }
}


class NewsProcessorService {
    private AIService $aiService;
    private ?SearchService $searchService; // SearchService可以是可选的
    private array $config;
    private array $prompts;

    public function __construct(AIService $aiService, ?SearchService $searchService = null) {
        $this->aiService = $aiService;
        $this->searchService = $searchService;
        $this->config = load_config();
        $this->prompts = $this->config['prompts'];
    }

    /**
     * 处理所有配置的RSS源
     */
    public function processRssFeeds(): void {
        $rssFeedsConfig = $this->config['rss_feeds'] ?? [];
        if (empty($rssFeedsConfig)) {
            log_message('info', "配置文件中没有找到RSS源。");
            return;
        }

        $rssParser = new SimpleRssParser();

        foreach ($rssFeedsConfig as $feedName => $feedUrl) {
            log_message('info', "开始处理RSS源: {$feedName} ({$feedUrl})");

            // 检查数据库中是否已存在此RSS源，如果不存在则添加
            $feedEntry = db_query_select("SELECT id, last_fetched_at FROM rss_feeds WHERE url = ?", ['s', $feedUrl]);
            $feedId = null;
            $lastFetchedDb = null;

            if (empty($feedEntry)) {
                $feedId = db_execute_query("INSERT INTO rss_feeds (name, url, is_active) VALUES (?, ?, ?)", ['ssi', $feedName, $feedUrl, 1], true);
                if ($feedId) {
                    log_message('info', "新的RSS源 '{$feedName}' 已添加到数据库，ID: {$feedId}");
                } else {
                    log_message('error', "无法将RSS源 '{$feedName}' 添加到数据库。");
                    continue;
                }
            } else {
                $feedId = $feedEntry[0]['id'];
                $lastFetchedDb = $feedEntry[0]['last_fetched_at'];
                // TODO: 可以根据 last_fetched_at 实现增量更新逻辑，避免重复处理旧闻
            }

            // 获取RSS内容
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $feedUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'NewsFromAI Bot/1.0 (+https://your-project-url.com)'); // 设置User-Agent
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 跟随重定向
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 有些源可能是自签名证书，实际生产中应谨慎
            $xmlContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError || $httpCode >= 400 || empty($xmlContent)) {
                log_message('error', "获取RSS源失败: {$feedName}", ['url' => $feedUrl, 'http_code' => $httpCode, 'error' => $curlError]);
                db_execute_query("UPDATE rss_feeds SET last_fetched_at = NOW() WHERE id = ?", ['i', $feedId]); // 更新抓取时间，即使失败
                continue;
            }

            $articles = $rssParser->parse($xmlContent);

            if (empty($articles)) {
                log_message('warning', "从RSS源解析文章失败或没有文章: {$feedName}", ['url' => $feedUrl]);
                db_execute_query("UPDATE rss_feeds SET last_fetched_at = NOW() WHERE id = ?", ['i', $feedId]);
                continue;
            }

            log_message('info', "从 '{$feedName}' 解析到 " . count($articles) . " 篇文章。");

            foreach ($articles as $article) {
                if (empty($article['link']) || empty($article['title'])) {
                    log_message('warning', "RSS条目缺少链接或标题，已跳过。", ['feed' => $feedName, 'article_data' => $article]);
                    continue;
                }

                // 检查新闻是否已存在 (基于URL)
                $existingArticle = db_query_select("SELECT id FROM news_articles WHERE source_url = ?", ['s', $article['link']]);
                if (!empty($existingArticle)) {
                    log_message('info', "新闻已存在，跳过: {$article['title']}", ['url' => $article['link']]);
                    continue;
                }

                $originalContent = strip_tags($article['description'] ?? ''); // 简单清理
                $publishedAt = !empty($article['pubDate']) ? date('Y-m-d H:i:s', strtotime($article['pubDate'])) : null;

                // AI摘要 (如果配置了 rss_summary 提示词)
                $aiSummary = $originalContent; // 默认使用原始内容
                if (isset($this->prompts['rss_summary']) && !empty(trim($originalContent))) {
                    $prompt = str_replace('{article_content}', $originalContent, $this->prompts['rss_summary']);
                    $summary = $this->aiService->get_ai_response($prompt, 'rss_summary'); // 使用 'rss_summary' AI配置
                    if ($summary) {
                        $aiSummary = $summary;
                        log_message('info', "AI已为文章生成摘要: {$article['title']}");
                    } else {
                        log_message('warning', "AI未能为文章生成摘要: {$article['title']}");
                    }
                    // 记录AI任务日志
                    db_execute_query(
                        "INSERT INTO ai_tasks_log (task_type, input_data_ref, status, prompt_used, ai_response_raw, error_message) VALUES (?, ?, ?, ?, ?, ?)",
                        ['ssssss', 'rss_summary', $article['link'], $summary ? 'success' : 'failure', $prompt, substr($summary ?? '', 0, 500), $summary ? null : 'AI did not return summary']
                    );
                }

                // AI设计呈现形式
                $presentationPrompt = str_replace(['{news_content}', '{related_info}'], [$aiSummary, "来源: {$feedName} - {$article['link']}"], $this->prompts['news_presentation_design']);
                $aiDesignedResponse = $this->aiService->get_ai_response($presentationPrompt, 'default'); // 使用默认AI配置

                $aiGeneratedContent = null; // 初始化
                $aiPresentationFormat = 'single_article'; // 默认格式
                $logStatus = 'failure'; // AI任务日志状态

                if ($aiDesignedResponse) {
                    $decodedResponse = json_decode($aiDesignedResponse, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($decodedResponse['format']) && isset($decodedResponse['content']) && is_string($decodedResponse['format']) && is_string($decodedResponse['content'])) {
                        $aiPresentationFormat = !empty(trim($decodedResponse['format'])) ? trim($decodedResponse['format']) : 'single_article';
                        $aiGeneratedContent = $decodedResponse['content'];
                        log_message('info', "AI成功解析呈现格式: {$aiPresentationFormat} for article: {$article['title']}");
                        $logStatus = 'success';
                    } else {
                        log_message('warning', "AI未能正确解析呈现格式JSON响应，或格式/内容为空。将使用AI原始响应（如果存在）作为内容，格式为single_article。", [
                            'article_title' => $article['title'],
                            'json_error' => json_last_error_msg(),
                            'response_received' => substr($aiDesignedResponse, 0, 500)
                        ]);
                        // 如果JSON解析失败，但AI有原始响应，则使用原始响应作为内容
                        $aiGeneratedContent = $aiDesignedResponse;
                    }
                }

                // 如果AI完全没有响应或者解析后内容为空，提供一个非常基础的回退
                if (empty($aiGeneratedContent)) {
                    log_message('warning', "AI未能为RSS文章设计呈现形式或内容为空，使用基础回退: {$article['title']}");
                    $aiGeneratedContent = "## " . htmlspecialchars($article['title']) . "\n\n" .
                                         htmlspecialchars($aiSummary) . "\n\n" .
                                         "[阅读原文](" . htmlspecialchars($article['link']) . ")";
                    $aiPresentationFormat = 'single_article'; // 确保回退时格式也设置
                }

                db_execute_query(
                    "INSERT INTO ai_tasks_log (task_type, input_data_ref, status, prompt_used, ai_response_raw, error_message) VALUES (?, ?, ?, ?, ?, ?)",
                    ['ssssss', 'presentation_design_rss', $article['link'], $logStatus, $presentationPrompt, substr($aiDesignedResponse ?? '', 0, 1000), $logStatus === 'success' ? null : 'AI did not return valid presentation JSON or JSON was malformed/empty']
                );

                // 存入数据库
                $insertedId = db_execute_query(
                    "INSERT INTO news_articles (title, source_url, source_name, original_content, ai_generated_content, ai_presentation_format, published_at, rss_feed_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    ['sssssssi', $article['title'], $article['link'], $feedName, $originalContent, $aiGeneratedContent, $aiPresentationFormat, $publishedAt, $feedId],
                    true
                );

                if ($insertedId) {
                    log_message('info', "RSS新闻已保存到数据库: {$article['title']}", ['id' => $insertedId]);
                } else {
                    log_message('error', "保存RSS新闻到数据库失败: {$article['title']}");
                }
            }
            db_execute_query("UPDATE rss_feeds SET last_fetched_at = NOW() WHERE id = ?", ['i', $feedId]);
            log_message('info', "RSS源 '{$feedName}' 处理完毕。");
        }
    }

    /**
     * AI主动搜寻新闻并处理
     */
    public function sourceNewsWithAI(): void {
        if (!$this->searchService) {
            log_message('warning', "SearchService未初始化，跳过AI主动新闻搜寻。");
            return;
        }
        if (empty($this->config['prompts']['news_sourcing_initial_topics'])) {
            log_message('info', "配置文件中没有找到 'news_sourcing_initial_topics'，跳过AI主动新闻搜寻。");
            return;
        }

        $initialTopics = $this->config['prompts']['news_sourcing_initial_topics'];

        foreach ($initialTopics as $topicPrompt) {
            log_message('info', "AI开始根据主题搜寻新闻: {$topicPrompt}");

            // 1. 让AI根据主题生成搜索关键词 (可选，也可以直接用主题作为关键词)
            //    或者，直接使用主题作为搜索查询
            $searchQuery = $topicPrompt; // 简化处理，直接用主题作为搜索查询

            // 2. 使用SearchService进行搜索
            $searchResults = $this->searchService->search($searchQuery, ['num' => 5]); // 获取前5条结果

            if (empty($searchResults)) {
                log_message('info', "未能从Google搜索获取到关于 '{$searchQuery}' 的结果。");
                continue;
            }

            log_message('info', "为主题 '{$topicPrompt}' 找到 " . count($searchResults) . " 条搜索结果。");

            foreach ($searchResults as $item) {
                if (empty($item['link']) || empty($item['title'])) {
                    continue;
                }
                // 检查新闻是否已存在 (基于URL)
                $existingArticle = db_query_select("SELECT id FROM news_articles WHERE source_url = ?", ['s', $item['link']]);
                if (!empty($existingArticle)) {
                    log_message('info', "搜索到的新闻已存在，跳过: {$item['title']}", ['url' => $item['link']]);
                    continue;
                }

                $newsContent = $item['title'] . "\n" . ($item['snippet'] ?? ''); // 简单组合标题和摘要
                // 实际应用中，可能需要抓取网页内容，但这会增加复杂性 (需要HTML解析库)
                // 为简化，这里仅使用搜索结果的摘要

                $relatedInfo = "来源: Google Search - " . htmlspecialchars($item['link']);
                if (isset($item['pagemap']['metatags'][0]['og:site_name'])) {
                     $relatedInfo = "来源: " . htmlspecialchars($item['pagemap']['metatags'][0]['og:site_name']) . " - " . htmlspecialchars($item['link']);
                }


                // 3. AI设计呈现形式
                $presentationPrompt = str_replace(['{news_content}', '{related_info}'], [$newsContent, $relatedInfo], $this->prompts['news_presentation_design']);
                $aiDesignedContent = $this->aiService->get_ai_response($presentationPrompt, 'news_sourcing'); // 使用 'news_sourcing' AI配置

                $taskId = db_execute_query(
                    "INSERT INTO ai_tasks_log (task_type, input_data_ref, status, prompt_used, ai_response_raw, error_message) VALUES (?, ?, ?, ?, ?, ?)",
                    ['ssssss', 'presentation_design_search', $item['link'], $aiDesignedContent ? 'success' : 'failure', $presentationPrompt, substr($aiDesignedContent ?? '', 0, 1000), $aiDesignedContent ? null : 'AI did not return presentation'],
                    true
                );


                if (!$aiDesignedContent) {
                    log_message('warning', "AI未能为搜索到的新闻设计呈现形式: {$item['title']}");
                    // 使用简化的Markdown作为回退
                     $aiDesignedContent = "## " . htmlspecialchars($item['title']) . "\n\n" .
                                         htmlspecialchars($item['snippet'] ?? '无摘要') . "\n\n" .
                                         "[阅读原文](" . htmlspecialchars($item['link']) . ")";
                }

                $publishedAt = null; // Google搜索结果通常不直接提供精确发布时间，除非从网页元数据提取

                // 4. 存入数据库
                $insertedId = db_execute_query(
                    "INSERT INTO news_articles (title, source_url, source_name, original_content, ai_generated_content, ai_presentation_format, published_at, search_keywords) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    ['ssssssss', $item['title'], $item['link'], ($item['displayLink'] ?? 'Google Search'), ($item['snippet'] ?? ''), $aiDesignedContent, 'single_article', $publishedAt, $searchQuery],
                    true
                );

                if ($insertedId) {
                    log_message('info', "AI搜寻的新闻已保存到数据库: {$item['title']}", ['id' => $insertedId]);
                } else {
                    log_message('error', "保存AI搜寻的新闻到数据库失败: {$item['title']}");
                }
            }
        }
    }
}
?>
