<?php
// core/ai_handler.php

// 确保配置文件已加载
if (!defined('DB_HOST')) { // 检查一个config.php中应存在的常量
    $configPath = __DIR__ . '/../config/config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    } else {
        // 如果没有config，这个模块无法正常工作
        // 在logger可用前，先用error_log
        error_log("ai_handler.php: 配置文件 config.php 未找到。");
        // 实际应用中可能需要更优雅地处理，比如抛出异常
        die("错误：AI模块配置文件缺失。");
    }
}

// 确保日志模块已加载
$loggerPath = __DIR__ . '/logger.php';
if (file_exists($loggerPath)) {
    require_once $loggerPath;
} else {
    // 如果没有logger，至少用error_log记录
    error_log("ai_handler.php: 日志模块 logger.php 未找到。");
    // 定义一个临时的 log_error 等函数，以防 logger.php 加载失败
    if (!function_exists('log_error')) {
        function log_info($msg){ error_log("INFO: ".$msg); }
        function log_warning($msg){ error_log("WARNING: ".$msg); }
        function log_error($msg){ error_log("ERROR: ".$msg); }
        function log_debug($msg){ error_log("DEBUG: ".$msg); }
    }
}

/**
 * 调用 OpenAI 兼容的聊天API
 *
 * @param string $apiUrl API的完整URL
 * @param string $apiKey API密钥
 * @param string $model 使用的模型，例如 'gpt-3.5-turbo'
 * @param string $userPrompt 用户当前的提示或问题
 * @param string|null $systemPrompt 系统提示词 (可选)
 * @param float $temperature 控制随机性的参数 (0.0 - 2.0)
 * @param int $maxTokens 最大生成token数
 * @return string|null AI的响应内容 (通常是JSON字符串中的 choices[0]['message']['content'])，或者在失败时返回null
 */
function call_openai_api(
    string $apiUrl,
    string $apiKey,
    string $model,
    string $userPrompt,
    ?string $systemPrompt = null,
    float $temperature = 0.7,
    int $maxTokens = 2048 // 增加默认的max_tokens，因为HTML内容可能较长
): ?string {
    log_debug("调用AI API: URL={$apiUrl}, Model={$model}");
    // 为了日志简洁，不完整记录prompt，特别是当它很长时
    // log_debug("SystemPrompt='{$systemPrompt}', UserPrompt='{$userPrompt}'");


    if (empty($apiKey)) {
        log_error("AI API密钥未提供。无法调用API: " . $apiUrl);
        return null;
    }
    if (empty($apiUrl)) {
        log_error("AI API URL未提供。无法调用API。");
        return null;
    }


    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ];

    $messages = [];
    if ($systemPrompt !== null && !empty(trim($systemPrompt))) {
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
    }
    $messages[] = ['role' => 'user', 'content' => $userPrompt];

    $data = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 180); // 进一步增加超时时间，AI响应和HTML生成可能较慢
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 1024 * 1024); // Set a larger buffer size (1MB) just in case
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // 生产环境建议开启
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);   // 生产环境建议开启

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        log_error("AI API cURL错误 ({$apiUrl}): " . $curlError);
        return null;
    }

    if ($httpCode !== 200) {
        log_error("AI API HTTP错误 ({$apiUrl}): Code={$httpCode}, Response=" . $response);
        return null;
    }

    // log_debug("AI API 原始响应 ({$apiUrl}): " . $response); // 响应可能非常长，调试时按需开启

    // Log the full raw response before attempting to decode
    log_debug("AI API Raw Response ({$apiUrl}): " . $response);
    // Save raw response to a temporary file for inspection
    // Ensure the logs directory exists and is writable, which should be handled by the logger setup.
    // Construct path relative to a known base directory if __DIR__ causes issues in certain execution contexts.
    // For now, assume __DIR__ is appropriate.
    $logBaseDir = defined('LOG_BASE_PATH') ? LOG_BASE_PATH : __DIR__ . '/../logs'; // Assuming LOG_BASE_PATH might be defined in config
    if (!is_dir($logBaseDir)) {
        @mkdir($logBaseDir, 0755, true);
    }
    $rawResponseFilename = 'ai_raw_response_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $model) . '.txt';
    $rawResponseFilePath = $logBaseDir . '/' . $rawResponseFilename;

    // Attempt to write the raw response.
    // Use a simplified timestamp for the content of the file itself.
    $fileContent = "URL: {$apiUrl}\nModel: {$model}\nTimestamp: " . date('Y-m-d H:i:s') . "\n\nResponse:\n{$response}";
    if (file_put_contents($rawResponseFilePath, $fileContent) === false) {
        log_warning("Failed to save raw AI response to: " . $rawResponseFilePath . ". Check directory permissions and path.");
    } else {
        log_info("Saved raw AI response to: " . $rawResponseFilePath);
    }

    $responseData = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        $jsonErrorMsg = json_last_error_msg();
        // Log a snippet of the response that caused the error
        $responseSnippetLength = 500;
        $responseSnippet = mb_substr($response, 0, $responseSnippetLength, 'UTF-8'); // Use mb_substr for UTF-8 safety
        log_error("AI API JSON解码错误 ({$apiUrl}): " . $jsonErrorMsg . ". Response (first {$responseSnippetLength} chars): " . $responseSnippet);
        // Log the path to the full raw response file again for easy access in case of JSON error
        log_error("Full raw response was saved to: " . $rawResponseFilePath);
        return null;
    }

    if (isset($responseData['choices'][0]['message']['content'])) {
        log_info("成功从AI API ({$apiUrl}) 获取响应。");
        $content = $responseData['choices'][0]['message']['content'];

        // Attempt to strip Markdown code blocks like ```json ... ``` or ``` ... ```
        // This regex looks for content between ```json (optional) and ```
        // It handles optional language specifier (like json) and potential newlines after ```json
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $content, $matches)) {
            $jsonContent = $matches[1];
            log_info("Stripped Markdown code block from AI response. Original length: " . strlen($content) . ", Stripped length: " . strlen($jsonContent));
            return trim($jsonContent);
        }
        // Fallback for cases where it might just be ``` without language specifier
        // This is largely covered by the above, but as a safety.
        // else if (preg_match('/^```\s*(.*?)\s*```$/s', $content, $matches)) {
        //     $jsonContent = $matches[1];
        //     log_info("Stripped Markdown code block (no lang spec) from AI response.");
        //     return trim($jsonContent);
        // }
        return $content; // Return original content if no Markdown block detected
    } elseif (isset($responseData['error']['message'])) {
        log_error("AI API ({$apiUrl}) 返回错误: " . $responseData['error']['message']);
        return null;
    } else {
        log_warning("AI API ({$apiUrl}) 响应格式未知或不包含预期的内容。完整响应：" . $response); // $response here is the full HTTP response, not just content
        return null;
    }
}

/**
 * 使用Google Custom Search API进行联网搜索
 *
 * @param string $apiKey Google API密钥
 * @param string $cx Programmable Search Engine ID (cx)
 * @param string $query 搜索查询词
 * @param int   $numResults 返回结果数量 (1-10)
 * @param array $options    其他查询参数，例如 ['sort' => 'date', 'dateRestrict' => 'd1']
 * @return array|null 搜索结果数组，或者在失败时返回null
 */
function google_search(string $apiKey, string $cx, string $query, int $numResults = 5, array $options = []): ?array {
    log_debug("执行Google搜索: Query='{$query}', NumResults={$numResults}");

    if (empty($apiKey) || empty($cx)) {
        log_error("Google Search API密钥或CX未配置。");
        return null;
    }

    $params = [
        'key' => $apiKey,
        'cx'  => $cx,
        'q'   => $query,
        'num' => max(1, min(10, $numResults)), // API限制1-10
    ];

    if (!empty($options['sort'])) {
        $params['sort'] = $options['sort'];
    }
    if (!empty($options['dateRestrict'])) {
        $params['dateRestrict'] = $options['dateRestrict'];
    }

    $apiUrl = "https://www.googleapis.com/customsearch/v1?" . http_build_query($params);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        log_error("Google Search API cURL错误: " . $curlError);
        return null;
    }

    if ($httpCode !== 200) {
        log_error("Google Search API HTTP错误: Code={$httpCode}, Response=" . $response);
        return null;
    }

    // log_debug("Google Search API 原始响应: " . $response); // 响应可能较长
    $responseData = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_error("Google Search API JSON解码错误: " . json_last_error_msg());
        return null;
    }

    if (isset($responseData['items'])) {
        log_info("成功从Google Search API获取 " . count($responseData['items']) . " 条结果。");
        return $responseData['items']; // 返回结果项数组
    } elseif (isset($responseData['error']['message'])) {
        log_error("Google Search API 返回错误: " . $responseData['error']['message']);
        return null;
    } elseif (isset($responseData['items']) && count($responseData['items']) === 0) {
        log_info("Google Search API 为查询 '{$query}' 返回了0条结果。");
        return [];
    } else {
        log_warning("Google Search API 响应格式未知或未返回 'items'。查询：'{$query}'，响应：" . $response);
        return []; // 返回空数组表示没有结果，区别于null表示的API错误
    }
}

/**
 * 替换提示词中的占位符
 * @param string $prompt 包含占位符的提示词，例如 "你好, {name}!"
 * @param array $placeholders 键值对数组，例如 ['name' => '世界']
 * @return string 替换后的提示词
 */
function fill_prompt_placeholders(string $prompt, array $placeholders): string {
    foreach ($placeholders as $key => $value) {
        // 确保替换的值是字符串，避免后续问题
        $value = is_array($value) || is_object($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
        $prompt = str_replace('{' . $key . '}', $value, $prompt);
    }
    return $prompt;
}


// 用法示例 (需要在有config.php和logger.php的环境下测试):
/*
// 确保配置和日志已加载
// require_once __DIR__ . '/../config/config.php';
// require_once __DIR__ . '/logger.php';

// Google 搜索示例
$searchResults = google_search(GOOGLE_SEARCH_API_KEY, GOOGLE_SEARCH_CX, "最新的AI技术", 3);
if ($searchResults !== null) {
    log_info("Google搜索结果数量: " . count($searchResults));
    $searchSummaryForAI = "";
    if (!empty($searchResults)) {
        foreach($searchResults as $idx => $item) {
            log_debug(" - 标题: " . ($item['title'] ?? 'N/A') . ", 链接: " . ($item['link'] ?? 'N/A'));
            $searchSummaryForAI .= ($idx+1).". Title: ".($item['title'] ?? 'N/A')."\n   Link: ".($item['link'] ?? 'N/A')."\n   Snippet: ".($item['snippet'] ?? 'N/A')."\n\n";
        }

        $userPromptForNewsFetch = fill_prompt_placeholders(NEWS_FETCH_AI_PROMPT, ['search_results' => $searchSummaryForAI]);

        $fetchedNewsDataJson = call_openai_api(
            NEWS_FETCH_AI_API_URL,
            NEWS_FETCH_AI_API_KEY,
            NEWS_FETCH_AI_MODEL,
            $userPromptForNewsFetch
        );

        if($fetchedNewsDataJson){
            log_info("新闻获取AI的响应: " . $fetchedNewsDataJson);
            // 后续处理 $fetchedNewsDataJson (应该是JSON字符串)
        } else {
            log_error("未能从新闻获取AI获取数据。");
        }
    } else {
        log_info("Google搜索没有返回结果。");
    }

} else {
    log_error("Google搜索失败或API配置错误。");
}

// AI 调用示例 (假设已有新闻标题和摘要)
$newsTitle = "示例新闻标题";
$newsSummary = "这是一条示例新闻的摘要内容。";

$commentPrompt = fill_prompt_placeholders(NEWS_COMMENT_AI_PROMPT, [
    'news_title' => $newsTitle,
    'news_summary' => $newsSummary
]);

$aiComment = call_openai_api(
    NEWS_COMMENT_AI_API_URL,
    NEWS_COMMENT_AI_API_KEY,
    NEWS_COMMENT_AI_MODEL,
    $commentPrompt
);

if ($aiComment) {
    log_info("AI评论: " . $aiComment);
} else {
    log_error("未能获取AI评论。");
}
*/

?>
