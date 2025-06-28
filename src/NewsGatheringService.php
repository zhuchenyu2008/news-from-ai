<?php
// news-from-ai/src/NewsGatheringService.php

namespace NewsFromAI;

class NewsGatheringService {
    private Config $config;
    // private Logger $logger; // Logger is static
    private ?GoogleSearch $googleSearch = null;
    private ?AIClient $newsGatheringAI = null;

    public function __construct(Config $config /*, Logger $logger (removed) */) {
        $this->config = $config;
        // $this->logger = $logger; // Removed

        $searchProvider = $this->config->get('news_sources.search_api_provider', 'google');
        if ($searchProvider === 'google') {
            $googleApiKey = $this->config->get('news_sources.google_search_api_key');
            $googleCxId = $this->config->get('news_sources.google_search_cx_id');
            if ($googleApiKey && $googleCxId) {
                // $this->googleSearch = new GoogleSearch($googleApiKey, $googleCxId, $this->logger);
                $this->googleSearch = new GoogleSearch($googleApiKey, $googleCxId);
            } else {
                Logger::warning("NewsGatheringService: Google Search API key or CX ID is not configured. AI news gathering via Google Search will be disabled.");
            }
        } // Future: else if ($searchProvider === 'another_provider') { ... }


        $aiApiKey = $this->config->get('ai_news_gathering.api_key', $this->config->get('ai_general.common_api_key'));
        $aiApiUrl = $this->config->get('ai_news_gathering.api_url', $this->config->get('ai_general.common_api_url'));
        $aiModel = $this->config->get('ai_news_gathering.model', 'gpt-3.5-turbo');

        if ($aiApiKey && $aiApiUrl) {
            // $this->newsGatheringAI = new AIClient($aiApiUrl, $aiApiKey, $aiModel, $this->logger);
            $this->newsGatheringAI = new AIClient($aiApiUrl, $aiApiKey, $aiModel);
        } else {
            Logger::error("NewsGatheringService: News Gathering AI API URL or Key is not configured. AI news gathering cannot proceed.");
        }
    }

    /**
     * 获取AI处理过的新闻线索
     * @param string $keywords 搜索关键词
     * @param int $numberOfArticlesToFetchFromSearch 从搜索引擎获取的文章数量上限
     * @param string $dateRestrict 搜索引擎日期限制
     * @return array|null 返回AI处理后的新闻数组，或在失败时返回null
     */
    public function fetchAINews(string $keywords, int $numberOfArticlesToFetchFromSearch = 5, string $dateRestrict = 'd1'): ?array {
        if (!$this->googleSearch) {
            $this->logger->error("NewsGatheringService: GoogleSearch client is not available. Cannot fetch news.");
            return null;
        }
        if (!$this->newsGatheringAI) {
            $this->logger->error("NewsGatheringService: News Gathering AI client is not available. Cannot process news.");
            return null;
        }

        $this->logger->info("NewsGatheringService: Starting AI news gathering process for keywords: '{$keywords}'");

        // 1. 使用Google Search API获取原始新闻链接和摘要
        $searchResults = $this->googleSearch->search($keywords, $numberOfArticlesToFetchFromSearch, $dateRestrict, 'date');

        if ($searchResults === null || empty($searchResults)) {
            $this->logger->warning("NewsGatheringService: No search results found from Google Search for keywords: '{$keywords}'.");
            return []; // 返回空数组表示没有找到，而不是错误
        }

        $this->logger->debug("NewsGatheringService: Found " . count($searchResults) . " potential articles from search.");

        // 准备将搜索结果传递给AI进行筛选和信息提取
        // AI的提示词期望的是一个包含关键词的上下文，以及它需要如何处理这些信息。
        // 当前的提示词设计是让AI自己进行搜索，但这里我们是先搜索，再把结果给AI。
        // 需要调整工作流或提示词。
        // 方案A：修改提示词，让AI基于提供的搜索结果工作。
        // 方案B：坚持让AI自行搜索（如果AI模型本身有联网能力或通过插件）。但题目要求是给AI配工具。
        // 我们选择方案A的变体：我们提供搜索结果，AI根据这些结果来“确认”和“提取结构化信息”。

        $promptTemplate = $this->config->get('ai_news_gathering.prompt');
        if (!$promptTemplate) {
            $this->logger->error("NewsGatheringService: News Gathering AI prompt is not configured.");
            return null;
        }

        // 为了让AI能处理我们提供的搜索结果，我们需要修改传递给AI的内容。
        // 当前的 "ai_news_gathering.prompt" 期望AI自己搜索。
        // 我们需要一个新的提示词或修改现有提示词的用法。
        // 暂时，我们将搜索结果包装一下，让AI“确认”这些信息。
        // 这不是最优的，理想情况下，提示词应该明确指示AI处理已有的搜索结果。
        // 我们假定提示词可以被调整为接受 `searchResults` 作为上下文。
        // 或者，更简单的方法是，让AI对每个搜索结果单独进行“细化”和“验证”，但这会增加API调用次数。

        // 鉴于提示词是 "请你扮演一个专业的新闻研究员...通过你拥有的搜索工具..."
        // 直接把我们的搜索结果作为上下文传给这个提示词可能不完全匹配。
        // 实际应用中，这个 "news_gathering" AI 可能更多的是一个 "news_validation_and_extraction" AI。

        // 暂时简化处理：假设AI的提示词能理解我们提供的上下文。
        // 我们将所有搜索结果的标题和摘要组合起来，让AI基于此工作。
        // 这需要 `ai_news_gathering.prompt` 能够处理 `search_results_context` 变量。
        $searchContextForAI = [];
        foreach ($searchResults as $result) {
            $searchContextForAI[] = [
                'title' => $result['title'],
                'snippet' => $result['snippet'],
                'link' => $result['link'],
                'source_name_guess' => $result['source_name'] // 我们初步猜测的来源
            ];
        }

        // **重要**: 当前的 `ai_news_gathering.prompt` 是让AI自行搜索并返回JSON。
        // 如果我们已经有了搜索结果，那么这个AI的角色就变成了从我们提供的结果中提取和格式化。
        // 我们需要一个不同的提示词，或者接受这个AI的输出可能与我们的输入高度相似。
        // 这里我们遵循提示词的字面意思：AI被告知关键词，然后它（理论上通过工具）返回新闻。
        // 所以，GoogleSearch的结果在这里仅作为一种“预筛选”或“辅助”，真正的选取和格式化由AI完成。
        // 但题目也说 “AI的联网搜索应该另外给AI配上工具使其可以调用”，我们实现的GoogleSearch就是这个工具。
        // 所以，流程应该是：
        // 1. NewsGatheringService 获取关键词。
        // 2. NewsGatheringService 调用 GoogleSearch (作为AI的工具)。
        // 3. NewsGatheringService 将GoogleSearch的结果 + 原始关键词 交给 NewsGatheringAI。
        // 4. NewsGatheringAI 根据其prompt（该prompt应指示AI分析提供的搜索结果）来生成最终的JSON。

        // 我们来调整 `variables` 以适应这种情况。
        // 新的提示词应该像这样：
        /*
        请你扮演一个专业的新闻研究员。
        我已经为你进行了一轮初步的互联网搜索，结果如下。请你基于这些结果，并结合你的判断：
        1.  **筛选**：选出其中最相关、最有价值的新闻。
        2.  **验证与补充**：如果可能，尝试确认信息的准确性（虽然你可能没有实时验证工具，但可以基于常识和来源判断）。补充缺失的信息，如准确的发布时间（如果搜索结果中有）。
        3.  **信息提取**：从选定的新闻中提取核心信息：标题、主要内容摘要（至少100字）、原文链接、发布时间和来源机构。
        4.  **结构化输出**：请将不多于3-5条最终选定的新闻以下列JSON格式组织起来。确保JSON格式正确无误。

        原始搜索关键词："{keywords}"
        初步搜索结果：
        ```json
        {searchResultsJson}
        ```

        输出格式如下：
        [
          {
            "title": "新闻标题",
            "summary": "新闻主要内容摘要（至少100字）",
            "url": "原文链接",
            "published_at": "发布日期和时间 (YYYY-MM-DD HH:MM:SS 格式，如果能获取到)",
            "source_name": "来源机构名称"
          }
        ]
        如果这些结果都不合适或质量不高，请返回一个空数组 []。
        */
        // 由于我们不能在这里修改配置文件中的提示词，我们将尽量适配当前的提示词。
        // 当前提示词是让AI自己搜索。这意味着我们的GoogleSearch结果实际上没有被直接使用。
        // 这与题目中 “AI的联网搜索应该另外给AI配上工具使其可以调用” 的部分描述有些矛盾，
        // 因为AI本身（通过API）通常不直接调用我们编写的PHP `GoogleSearch` 类。
        // 除非这个AI是一个特殊的Agent，可以被赋予调用外部HTTP API的工具。
        // 假设OpenAI API支持函数调用/工具使用，那么 `GoogleSearch->search` 应该被注册为一个可供AI调用的工具。
        // 但目前 `AIClient` 是一个通用的HTTP请求客户端。

        // 为了让项目能跑起来，我们暂时让 NewsGatheringAI 直接基于关键词工作，
        // 忽略我们自己用 `GoogleSearch` 得到的结果，除非AI提示词明确要求它处理我们提供的结果。
        // 题目描述 "AI根据用户设置的新闻关键词自行上网搜索新闻"，这里的 "自行上网搜索" 由 GoogleSearch 实现，
        // 然后 "把这些原始数据交一份给..." -> 这意味着 GoogleSearch 的结果是第一步。
        // "AI获取最新新闻并格式清晰的记录下来" -> 这是 NewsGatheringAI 的职责，输入是GoogleSearch的结果。

        // 所以，我将假设 `ai_news_gathering.prompt` 应该被修改成我上面设计的样子。
        // 在当前代码中，我将使用一个适配的变量名。
        $variables = [
            'keywords' => $keywords,
            // 我们需要将 $searchResults 传递给AI。提示词需要一个对应的占位符。
            // 假设提示词中有 {preliminary_search_results}
            'preliminary_search_results' => json_encode($searchResults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ];

        // 如果config中的prompt就是让AI自己搜索的那个，那么 `preliminary_search_results` 就不会被用到。
        // 我们需要用户在config中配置一个能处理 `preliminary_search_results` 的prompt。
        // 例如，config中的prompt可以是:
        // "基于以下初步搜索结果：{preliminary_search_results}，以及原始关键词 '{keywords}'，请筛选、验证并提取新闻信息，输出JSON..."

        $aiResponseJson = $this->newsGatheringAI->sendRequest($promptTemplate, $variables, "", 0.5, 3000);

        if ($aiResponseJson === null) {
            $this->logger->error("NewsGatheringService: Failed to get response from News Gathering AI for keywords: '{$keywords}'.");
            return null;
        }

        $this->logger->debug("NewsGatheringService: Received response from AI: " . $aiResponseJson);

        $processedNews = json_decode($aiResponseJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("NewsGatheringService: Failed to decode JSON response from News Gathering AI.", ['json_error' => json_last_error_msg(), 'raw_response' => $aiResponseJson]);
            // 尝试修复非标准JSON，例如AI可能返回了被额外引号包裹的JSON字符串
            if (is_string($aiResponseJson) && trim($aiResponseJson, '"') !== $aiResponseJson) {
                $this->logger->info("NewsGatheringService: Attempting to fix JSON by trimming quotes.");
                $processedNews = json_decode(trim($aiResponseJson, '"'), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                     $this->logger->error("NewsGatheringService: Still failed to decode JSON after trimming quotes.");
                     return null;
                }
            } else {
                 return null;
            }
        }

        if (!is_array($processedNews)) {
            $this->logger->error("NewsGatheringService: AI response is not a valid array after JSON decode.", ['decoded_response' => $processedNews]);
            return null;
        }

        $this->logger->info("NewsGatheringService: Successfully processed " . count($processedNews) . " news items from AI for keywords: '{$keywords}'.");

        // 对AI返回的数据进行基本验证和清理
        $validatedNews = [];
        foreach ($processedNews as $newsItem) {
            if (isset($newsItem['title']) && isset($newsItem['summary']) && isset($newsItem['url'])) {
                // 确保 published_at 格式正确，如果存在
                if (isset($newsItem['published_at']) && !empty($newsItem['published_at'])) {
                    try {
                        $dt = new \DateTime($newsItem['published_at']);
                        $newsItem['published_at'] = $dt->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        $this->logger->warning("NewsGatheringService: Invalid date format from AI for 'published_at'", ['value' => $newsItem['published_at'], 'title' => $newsItem['title']]);
                        $newsItem['published_at'] = null; //或设置为当前时间，或尝试从原始搜索结果补充
                    }
                } else {
                    $newsItem['published_at'] = null;
                }
                $newsItem['source_name'] = $newsItem['source_name'] ?? 'N/A';
                $validatedNews[] = $newsItem;
            } else {
                $this->logger->warning("NewsGatheringService: AI returned an item with missing required fields (title, summary, or url). Skipping.", ['item' => $newsItem]);
            }
        }

        return $validatedNews;
    }
}
?>
