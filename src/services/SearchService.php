<?php
// news-from-ai - 搜索引擎服务类

require_once dirname(__DIR__) . '/includes/functions.php';

class SearchService {
    private array $config;
    private string $apiKey;
    private string $cseId;

    public function __construct() {
        $this->config = load_config();
        if (empty($this->config['google_search']['api_key']) || empty($this->config['google_search']['cse_id']) ||
            $this->config['google_search']['api_key'] === 'YOUR_GOOGLE_SEARCH_API_KEY' ||
            $this->config['google_search']['cse_id'] === 'YOUR_GOOGLE_CUSTOM_SEARCH_ENGINE_ID') {
            $msg = "Google Custom Search API Key或CSE ID未配置或使用的是占位符。";
            log_message('warning', $msg);
            // 不抛出异常，允许应用在没有搜索功能的情况下继续运行（如果设计允许）
            // 但搜索功能将不可用
            $this->apiKey = '';
            $this->cseId = '';
        } else {
            $this->apiKey = $this->config['google_search']['api_key'];
            $this->cseId = $this->config['google_search']['cse_id'];
        }
    }

    /**
     * 使用Google Custom Search API执行搜索
     *
     * @param string $query 搜索查询词
     * @param array $options 额外的API参数 (如 num, start, siteSearch)
     * @return array|null 搜索结果项的数组，或在失败时返回null
     */
    public function search(string $query, array $options = []): ?array {
        if (empty($this->apiKey) || empty($this->cseId)) {
            log_message('error', 'Google Search API key或CSE ID未配置，无法执行搜索。');
            return null;
        }

        $apiUrl = "https://www.googleapis.com/customsearch/v1";
        $params = [
            'key' => $this->apiKey,
            'cx' => $this->cseId,
            'q' => $query,
            'num' => $options['num'] ?? 10, // 默认返回10条结果
        ];

        // 合并其他可选参数
        if (isset($options['start'])) $params['start'] = $options['start'];
        if (isset($options['lr'])) $params['lr'] = $options['lr']; // language restriction
        if (isset($options['sort'])) $params['sort'] = $options['sort']; // sort expression
        if (isset($options['siteSearch'])) $params['siteSearch'] = $options['siteSearch']; // restrict to site

        $urlWithParams = $apiUrl . '?' . http_build_query($params);

        $ch = curl_init($urlWithParams);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30秒超时
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $endTime = microtime(true);
        $executionTimeMs = round(($endTime - $startTime) * 1000);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        log_message('debug', "Google Search API请求详情", [
            'query' => $query,
            'url' => $urlWithParams,
            'http_code' => $httpCode,
            'execution_time_ms' => $executionTimeMs
        ]);

        if ($curlError) {
            log_message('error', "Google Search API cURL错误: " . $curlError, ['url' => $urlWithParams]);
            return null;
        }

        if ($httpCode >= 400) {
            log_message('error', "Google Search API HTTP错误: " . $httpCode, ['url' => $urlWithParams, 'response' => $response]);
            return null;
        }

        $responseData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message('error', "Google Search API JSON解码失败: " . json_last_error_msg(), ['response_preview' => substr($response, 0, 200)]);
            return null;
        }

        if (isset($responseData['error'])) {
            log_message('error', "Google Search API返回错误: " . $responseData['error']['message'], ['error_details' => $responseData['error']]);
            return null;
        }

        // 返回搜索结果中的 'items' 数组
        // 每个item通常包含 title, link, snippet, etc.
        if (isset($responseData['items'])) {
            log_message('info', "Google Search API成功获取 " . count($responseData['items']) . " 条结果 for query: " . $query);
            return $responseData['items'];
        } elseif (isset($responseData['queries'])) {
            // Query was valid, but no results found
            log_message('info', "Google Search API没有找到结果 for query: " . $query);
            return [];
        } else {
            log_message('warning', "Google Search API响应中未找到预期的'items'或'queries'结构", ['response_data' => $responseData]);
            return null;
        }
    }
}
?>
