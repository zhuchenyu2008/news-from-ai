<?php
// news-from-ai/src/NewsProcessingService.php

namespace NewsFromAI;

// Ensure lib/Parsedown.php is available.
// If not using an autoloader that handles non-PSR-4 class files, you might need to require it.
require_once __DIR__ . '/lib/Parsedown.php'; // Assuming Parsedown.php is in src/lib/

use Exception;
use Parsedown; // 用于将Markdown转换为HTML

class NewsProcessingService {
    private Config $config; // Assuming Config class is autoloaded or included
    private Logger $logger;
    private Database $db;
    private ?NewsGatheringService $newsGatheringService = null;
    private ?RSSReader $rssReader = null;
    private ?AIClient $commentingAI = null;
    private ?AIClient $summarizingAI = null;
    private Parsedown $markdownParser;


    public function __construct(Config $config, Logger $logger, Database $db) {
        $this->config = $config;
        $this->logger = $logger;
        $this->db = $db;
        $this->markdownParser = new Parsedown();


        // 初始化 NewsGatheringService (如果配置允许)
        // (NewsGatheringService 内部会检查其依赖项如GoogleSearch和GatheringAI的配置)
        $this->newsGatheringService = new NewsGatheringService($this->config, $this->logger);

        // 初始化 RSSReader
        $this->rssReader = new RSSReader($this->logger);

        // 初始化评论AI
        $commentingAiApiKey = $this->config->get('ai_news_commenting.api_key', $this->config->get('ai_general.common_api_key'));
        $commentingAiApiUrl = $this->config->get('ai_news_commenting.api_url', $this->config->get('ai_general.common_api_url'));
        $commentingAiModel = $this->config->get('ai_news_commenting.model', 'gpt-3.5-turbo');
        if ($commentingAiApiKey && $commentingAiApiUrl) {
            $this->commentingAI = new AIClient($commentingAiApiUrl, $commentingAiApiKey, $commentingAiModel, $this->logger);
        } else {
            $this->logger->error("NewsProcessingService: Commenting AI API URL or Key is not configured. Commenting feature will be disabled.");
        }

        // 初始化整理汇总AI
        $summarizingAiApiKey = $this->config->get('ai_news_summarizing.api_key', $this->config->get('ai_general.common_api_key'));
        $summarizingAiApiUrl = $this->config->get('ai_news_summarizing.api_url', $this->config->get('ai_general.common_api_url'));
        $summarizingAiModel = $this->config->get('ai_news_summarizing.model', 'gpt-4'); // 通常需要更强的模型
        if ($summarizingAiApiKey && $summarizingAiApiUrl) {
            $this->summarizingAI = new AIClient($summarizingAiApiUrl, $summarizingAiApiKey, $summarizingAiModel, $this->logger);
        } else {
            $this->logger->error("NewsProcessingService: Summarizing AI API URL or Key is not configured. Summarizing feature will be disabled.");
        }
    }

    /**
     * 处理AI搜索的新闻
     * @param string $keywords
     * @return int 成功处理并存储的新闻数量
     */
    public function processAISearchNews(string $keywords): int {
        if (!$this->newsGatheringService) {
            $this->logger->error("NewsProcessingService: NewsGatheringService is not available. Cannot process AI Search News.");
            return 0;
        }
        if (!$this->commentingAI || !$this->summarizingAI) {
            $this->logger->error("NewsProcessingService: Commenting or Summarizing AI is not available. Cannot fully process AI Search News.");
            return 0;
        }

        $this->logger->info("NewsProcessingService: Starting processing of AI Search News for keywords: '{$keywords}'");
        $gatheredNewsItems = $this->newsGatheringService->fetchAINews($keywords);

        if ($gatheredNewsItems === null) {
            $this->logger->error("NewsProcessingService: Failed to gather news for keywords '{$keywords}'.");
            return 0;
        }
        if (empty($gatheredNewsItems)) {
            $this->logger->info("NewsProcessingService: No news items gathered by AI for keywords '{$keywords}'.");
            return 0;
        }

        $processedCount = 0;
        foreach ($gatheredNewsItems as $newsItem) {
            try {
                // 检查新闻是否已存在 (基于URL)
                if ($this->isNewsItemExists($newsItem['url'])) {
                    $this->logger->info("NewsProcessingService: News item already exists, skipping.", ['url' => $newsItem['url'], 'title' => $newsItem['title']]);
                    continue;
                }

                // 1. 获取AI评论
                $commentPromptTemplate = $this->config->get('ai_news_commenting.prompt');
                $commentVariables = [
                    'title' => $newsItem['title'],
                    'summary' => $newsItem['summary'], // 这是AI news gathering 返回的summary
                    'source_name' => $newsItem['source_name'] ?? 'N/A',
                    'published_at' => $newsItem['published_at'] ?? 'N/A',
                ];
                $aiComment = $this->commentingAI->sendRequest($commentPromptTemplate, $commentVariables);

                if (!$aiComment) {
                    $this->logger->warning("NewsProcessingService: Failed to get AI comment for news item.", ['title' => $newsItem['title']]);
                    $aiComment = "AI评论获取失败。"; // 设置默认值或跳过
                }

                // 2. 获取AI整理汇总的HTML内容
                $summarizingPromptTemplate = $this->config->get('ai_news_summarizing.prompt');
                $summarizingVariables = [
                    'title' => $newsItem['title'],
                    'summary' => $newsItem['summary'], // AI news gathering 返回的summary
                    'source_name' => $newsItem['source_name'] ?? 'N/A',
                    'url' => $newsItem['url'],
                    'published_at' => $newsItem['published_at'] ?? 'N/A',
                    'ai_comment' => $aiComment,
                     // 'raw_search_result_snippet' => $newsItem['raw_item']['snippet'] ?? $newsItem['summary'], // 可以提供更多原始上下文
                ];
                $contentHtml = $this->summarizingAI->sendRequest($summarizingPromptTemplate, $summarizingVariables, "", 0.7, 3500);

                if (!$contentHtml) {
                    $this->logger->warning("NewsProcessingService: Failed to get AI summarized HTML content for news item.", ['title' => $newsItem['title']]);
                    // 可以选择跳过，或者使用一个非常基础的模板
                    $contentHtml = "<p>AI内容生成失败。请查看原文。</p>";
                } else {
                    // 确保AI输出的是HTML，如果它返回了Markdown，则进行转换
                    // 简单的检查，如果看起来不像HTML标签开头，则假定为Markdown
                    if (strpos(trim($contentHtml), '<') !== 0) {
                        $this->logger->info("NewsProcessingService: Summarizing AI returned Markdown, converting to HTML.", ['title' => $newsItem['title']]);
                        $contentHtml = $this->markdownParser->text($contentHtml);
                    }
                }

                // 替换占位符图片URL
                $placeholderImageUrl = $this->config->get('developer.placeholder_image_url', 'public/images/placeholder.png');
                // 如果 $placeholderImageUrl 是相对路径，需要转为相对于web根目录的路径
                // 假设 `public/` 是web根目录，配置文件中的 `public/images/placeholder.png` 可以被CSS/HTML直接引用为 `images/placeholder.png`
                // 如果在 `src` 目录中，路径可能需要调整，或者在前端模板中处理
                // 这里我们假设AI生成的占位符是 `<img src="placeholder_image.jpg" ...>`
                // 我们需要将其替换为配置文件中定义的实际占位符路径
                $webPlaceholderPath = str_replace('public/', '', $placeholderImageUrl); // e.g. images/placeholder.png

                $contentHtml = str_replace(
                    ['src="placeholder_image.jpg"', "src='placeholder_image.jpg'"],
                    ['src="' . $webPlaceholderPath . '"', "src='" . $webPlaceholderPath . "'"],
                    $contentHtml
                );


                // 3. 存储到数据库
                $this->saveNewsItem(
                    $newsItem['title'],
                    $contentHtml,
                    $aiComment,
                    $newsItem['url'],
                    $newsItem['source_name'] ?? null,
                    $newsItem['published_at'] ?? null,
                    'ai_search',
                    $keywords,
                    null, // rss_feed_id
                    null, // feed_item_guid
                    json_encode($newsItem, JSON_UNESCAPED_UNICODE) // raw_data_json
                );
                $processedCount++;
                $this->logger->info("NewsProcessingService: Successfully processed and saved AI search news.", ['title' => $newsItem['title']]);

            } catch (Exception $e) {
                $this->logger->error("NewsProcessingService: Exception while processing AI search news item.", [
                    'title' => $newsItem['title'] ?? 'N/A',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        $this->logger->info("NewsProcessingService: Finished processing AI Search News for keywords '{$keywords}'. Processed {$processedCount} items.");
        return $processedCount;
    }

    /**
     * 处理来自特定RSS源的新闻
     * @param int $feedId RSS源的ID
     * @param string $feedUrl RSS源的URL
     * @param string $feedName RSS源的名称 (用于日志)
     * @return int 成功处理并存储的新闻数量
     */
    public function processRSSFeedNews(int $feedId, string $feedUrl, string $feedName): int {
        if (!$this->rssReader) {
            $this->logger->error("NewsProcessingService: RSSReader is not available.");
            return 0;
        }
        if (!$this->commentingAI || !$this->summarizingAI) {
            $this->logger->error("NewsProcessingService: Commenting or Summarizing AI is not available. Cannot fully process RSS News.");
            return 0;
        }

        $articlesPerFeed = (int)$this->config->get('rss.articles_per_feed', 5);
        $this->logger->info("NewsProcessingService: Starting processing of RSS feed: {$feedName}", ['url' => $feedUrl]);

        $feedData = $this->rssReader->fetchFeed($feedUrl, $articlesPerFeed);

        if ($feedData === null || empty($feedData['items'])) {
            $this->logger->warning("NewsProcessingService: No items fetched or failed to fetch from RSS feed: {$feedName}", ['url' => $feedUrl]);
            Database::execute("UPDATE rss_feeds SET last_fetched_at = NOW(), last_error = ? WHERE id = ?", ["Failed to fetch or no items", $feedId]);
            return 0;
        }

        Database::execute("UPDATE rss_feeds SET last_fetched_at = NOW(), last_error = NULL, description = ? WHERE id = ?", [$feedData['metadata']['description'] ?? null, $feedId]);


        $processedCount = 0;
        foreach ($feedData['items'] as $item) {
            try {
                // 检查RSS条目是否已处理 (基于 feed_id 和 guid)
                if ($this->isRssItemProcessed($feedId, $item['guid'])) {
                    $this->logger->info("NewsProcessingService: RSS item already processed, skipping.", ['guid' => $item['guid'], 'title' => $item['title']]);
                    continue;
                }

                // 1. 获取AI评论 (使用RSS特定的提示词，如果配置了)
                $commentPromptTemplate = $this->config->get('rss.rss_ai_comment_prompt', $this->config->get('ai_news_commenting.prompt'));
                $commentVariables = [
                    'title' => $item['title'],
                    'link' => $item['link'],
                    'pubDate' => $item['pubDate'] ?? 'N/A',
                    'description' => $item['description'], // 这是RSS item的description
                    // 'content' => substr(strip_tags($item['content']), 0, 1000) // 可以提供部分内容给AI评论
                ];
                $aiComment = $this->commentingAI->sendRequest($commentPromptTemplate, $commentVariables);

                if (!$aiComment) {
                    $this->logger->warning("NewsProcessingService: Failed to get AI comment for RSS item.", ['title' => $item['title']]);
                    $aiComment = "AI评论获取失败。";
                }

                // 2. 获取AI整理汇总的HTML内容 (使用RSS特定的提示词)
                $summarizingPromptTemplate = $this->config->get('rss.rss_ai_summary_prompt', $this->config->get('ai_news_summarizing.prompt'));
                $summarizingVariables = [
                    'title' => $item['title'],
                    'link' => $item['link'],
                    'pubDate' => $item['pubDate'] ?? 'N/A',
                    'description' => $item['description'],
                    'content' => $item['content'], // RSS item的完整内容 (可能含HTML)
                    'ai_comment' => $aiComment,
                ];
                $contentHtml = $this->summarizingAI->sendRequest($summarizingPromptTemplate, $summarizingVariables, "", 0.7, 3000);

                if (!$contentHtml) {
                    $this->logger->warning("NewsProcessingService: Failed to get AI summarized HTML content for RSS item.", ['title' => $item['title']]);
                    $contentHtml = "<p>AI内容生成失败。请查看原文。</p>";
                } else {
                     if (strpos(trim($contentHtml), '<') !== 0) {
                        $this->logger->info("NewsProcessingService: RSS Summarizing AI returned Markdown, converting to HTML.", ['title' => $item['title']]);
                        $contentHtml = $this->markdownParser->text($contentHtml);
                    }
                }
                 // 替换占位符图片URL (RSS内容可能本身包含图片，AI也可能建议图片)
                $placeholderImageUrl = $this->config->get('developer.placeholder_image_url', 'public/images/placeholder.png');
                $webPlaceholderPath = str_replace('public/', '', $placeholderImageUrl);
                $contentHtml = str_replace(
                    ['src="placeholder_image.jpg"', "src='placeholder_image.jpg'"],
                    ['src="' . $webPlaceholderPath . '"', "src='" . $webPlaceholderPath . "'"],
                    $contentHtml
                );


                // 3. 存储到数据库
                $this->saveNewsItem(
                    $item['title'],
                    $contentHtml,
                    $aiComment,
                    $item['link'],
                    $feedData['metadata']['title'] ?? $feedName, // source_name 使用 feed 的标题
                    $item['pubDate'], // 解析过的日期
                    'rss',
                    null, // keywords
                    $feedId,
                    $item['guid'],
                    json_encode($item, JSON_UNESCAPED_UNICODE) // raw_data_json
                );
                $processedCount++;
                $this->logger->info("NewsProcessingService: Successfully processed and saved RSS item.", ['title' => $item['title'], 'feed' => $feedName]);

            } catch (Exception $e) {
                $this->logger->error("NewsProcessingService: Exception while processing RSS item.", [
                    'title' => $item['title'] ?? 'N/A',
                    'feed' => $feedName,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        $this->logger->info("NewsProcessingService: Finished processing RSS feed '{$feedName}'. Processed {$processedCount} items.");
        return $processedCount;
    }

    /**
     * 检查指定URL的新闻是否已存在数据库中
     * @param string $url
     * @return bool
     */
    private function isNewsItemExists(string $url): bool {
        $count = Database::fetchColumn("SELECT COUNT(*) FROM news WHERE source_url = ?", [$url]);
        return $count > 0;
    }

    /**
     * 检查指定的RSS条目是否已被处理
     * @param int $feedId
     * @param string $guid
     * @return bool
     */
    private function isRssItemProcessed(int $feedId, string $guid): bool {
        if (empty($guid)) { // 如果guid为空，则无法可靠判断，为避免重复，可考虑基于URL判断
            $this->logger->warning("RSS item has empty GUID for feed ID {$feedId}. Uniqueness check might be unreliable.");
            return false; // 或者如果URL也检查，则 return $this->isNewsItemExists($link);
        }
        $count = Database::fetchColumn("SELECT COUNT(*) FROM news WHERE rss_feed_id = ? AND feed_item_guid = ?", [$feedId, $guid]);
        return $count > 0;
    }

    /**
     * 保存新闻条目到数据库
     */
    private function saveNewsItem(
        string $title,
        string $contentHtml,
        string $aiComment,
        string $sourceUrl,
        ?string $sourceName,
        ?string $publishedAt, // 格式 'Y-m-d H:i:s'
        string $newsType,
        ?string $keywords,
        ?int $rssFeedId,
        ?string $feedItemGuid,
        ?string $rawDataJson
    ): bool {
        $sql = "INSERT INTO news (title, content_html, ai_comment, source_url, source_name, published_at, news_type, keywords, rss_feed_id, feed_item_guid, raw_data_json, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $title,
            $contentHtml,
            $aiComment,
            $sourceUrl,
            $sourceName,
            $publishedAt, // 确保是 Y-m-d H:i:s 或 null
            $newsType,
            $keywords,
            $rssFeedId,
            $feedItemGuid,
            $rawDataJson
        ];

        $result = Database::execute($sql, $params);
        if ($result === false) {
            $this->logger->error("NewsProcessingService: Failed to save news item to database.", ['title' => $title, 'sql_error' => Database::getConnection() ? Database::getConnection()->errorInfo()[2] : 'N/A']);
            return false;
        }
        return $result > 0;
    }

    /**
     * 获取用于在首页展示的新闻
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getHomepageNews(int $limit = 10, int $offset = 0): array {
        $sql = "SELECT id, title, content_html, ai_comment, source_url, source_name, published_at, created_at, news_type
                FROM news
                ORDER BY COALESCE(published_at, created_at) DESC, id DESC
                LIMIT ? OFFSET ?";
        $newsItems = Database::fetchAll($sql, [$limit, $offset]);

        if ($newsItems === false) {
            $this->logger->error("NewsProcessingService: Failed to fetch homepage news from database.");
            return [];
        }
        return $newsItems;
    }
}

// 需要一个 Config 类来加载和解析 config.ini
// 为了让 NewsProcessingService 能运行，我们先创建一个简单的 Config 类
// The Config class definition has been moved to src/Config.php
?>
