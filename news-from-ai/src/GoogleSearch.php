<?php
// news-from-ai/src/GoogleSearch.php

namespace NewsFromAI;

class GoogleSearch {
    private string $apiKey;
    private string $cxId;
    // private Logger $logger; // Logger is static
    private const API_ENDPOINT = 'https://www.googleapis.com/customsearch/v1';

    /**
     * GoogleSearch constructor.
     * @param string $apiKey Google API Key
     * @param string $cxId Custom Search Engine ID
     * // @param Logger $logger Logger instance (removed)
     */
    public function __construct(string $apiKey, string $cxId) {
        $this->apiKey = $apiKey;
        $this->cxId = $cxId;
        // $this->logger = $logger; // Removed

        if (empty($this->apiKey) || empty($this->cxId)) {
            Logger::warning("GoogleSearch: API Key or CX ID is not configured. Search calls will fail.");
        }
    }

    /**
     * 执行搜索
     * @param string $query 搜索查询词
     * @param int $numResults 希望返回的结果数量 (Google API限制每次最多10个)
     * @param string $dateRestrict 限制搜索结果的日期范围 (e.g., 'd1' for last 24 hours, 'w1' for last week, 'm1' for last month)
     *                           参考: https://developers.google.com/custom-search/v1/reference/rest/v1/cse/list#dateRestrict
     * @param string $sort (可选) 排序方式, e.g., 'date'
     * @return array|null 搜索结果数组，失败则返回null
     */
    public function search(string $query, int $numResults = 5, string $dateRestrict = 'd7', string $sort = 'date'): ?array {
        if (empty($this->apiKey) || empty($this->cxId)) {
            Logger::error("GoogleSearch: API Key or CX ID is not configured. Cannot perform search.");
            return null;
        }

        if ($numResults > 10) {
            Logger::warning("GoogleSearch: Requested {$numResults} results, but API limit is 10. Will fetch 10.");
            $numResults = 10;
        }
        if ($numResults <= 0) {
            $numResults = 5; // Default to 5 if invalid
        }

        $params = [
            'key' => $this->apiKey,
            'cx' => $this->cxId,
            'q' => $query,
            'num' => $numResults,
        ];

        if (!empty($dateRestrict)) {
            $params['dateRestrict'] = $dateRestrict;
        }
        if (!empty($sort)) {
            // Google Custom Search API 支持 'date' 排序
            // https://developers.google.com/custom-search/docs/structured_search#sort_by_attribute
            // 对于新闻，按日期排序通常是期望的行为
            $params['sort'] = 'date';
        }


        $url = self::API_ENDPOINT . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);  // 连接超时5秒
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);       // 总执行超时20秒

        Logger::debug("GoogleSearch: Sending search request", ['query' => $query, 'num_results' => $numResults, 'url' => $url]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Logger::error("GoogleSearch: cURL Error: " . $curlError, ['url' => $url]);
            return null;
        }

        if ($httpCode >= 400) {
            Logger::error("GoogleSearch: API request failed with HTTP code {$httpCode}.", ['url' => $url, 'response_body' => $response]);
            return null;
        }

        $responseData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::error("GoogleSearch: Failed to decode JSON response.", ['url' => $url, 'response_body' => $response]);
            return null;
        }

        if (isset($responseData['error'])) {
            Logger::error("GoogleSearch: API returned an error.", [
                'error' => $responseData['error']['message'] ?? 'Unknown error',
                'code' => $responseData['error']['code'] ?? 0,
                'details' => $responseData['error']['errors'] ?? []
            ]);
            return null;
        }

        Logger::info("GoogleSearch: Successfully performed search for '{$query}'. Found " . count($responseData['items'] ?? []) . " items.");

        // 提取并返回需要的信息
        $results = [];
        if (isset($responseData['items'])) {
            foreach ($responseData['items'] as $item) {
                // 尝试从pagemap中获取更详细的发布日期和来源
                $publishedDate = null;
                $sourceName = $item['displayLink'] ?? null; // 默认使用displayLink

                if (isset($item['pagemap']['metatags'][0])) {
                    $metatags = $item['pagemap']['metatags'][0];
                    // 尝试多种可能的日期字段
                    $dateFields = ['article:published_time', 'og:article:published_time', 'publishdate', 'dc.date.issued', 'datepublished', 'timestamp'];
                    foreach ($dateFields as $field) {
                        if (!empty($metatags[$field])) {
                            // 尝试将获取到的日期转换为标准格式
                            try {
                                $dt = new \DateTime($metatags[$field]);
                                $publishedDate = $dt->format('Y-m-d H:i:s');
                                break;
                            } catch (\Exception $e) {
                                // 无法解析，继续尝试下一个
                            }
                        }
                    }
                    // 尝试获取来源机构名称
                    $sourceNameFields = ['og:site_name', 'application-name', 'twitter:site'];
                     foreach ($sourceNameFields as $field) {
                        if (!empty($metatags[$field])) {
                            $sourceName = $metatags[$field];
                            break;
                        }
                    }
                }
                // 如果没有从metatags获取到精确时间，尝试从snippet中提取，或使用API提供的粗略时间（如果有）
                // Google CSE API本身不直接返回精确的发布时间戳，依赖于网页的元数据

                $results[] = [
                    'title' => $item['title'] ?? 'N/A',
                    'link' => $item['link'] ?? '#',
                    'snippet' => $item['snippet'] ?? '', // 摘要
                    'htmlSnippet' => $item['htmlSnippet'] ?? '', // HTML格式摘要
                    'source_name' => $sourceName,
                    'published_at' => $publishedDate, // 可能为null
                    'raw_item' => $item // 保留原始item，AI可能需要更多上下文
                ];
            }
        }
        return $results;
    }
}
?>
