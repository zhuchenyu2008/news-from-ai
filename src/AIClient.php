<?php
// news-from-ai/src/AIClient.php

namespace NewsFromAI;

use Exception;

class AIClient {
    private string $apiUrl;
    private string $apiKey;
    private string $model;
    // private Logger $logger; // Logger is static

    /**
     * AIClient constructor.
     * @param string $apiUrl API的URL
     * @param string $apiKey API密钥
     * @param string $model 使用的模型
     * // @param Logger $logger 日志记录器实例 (removed)
     */
    public function __construct(string $apiUrl, string $apiKey, string $model) {
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
        $this->model = $model;
        // $this->logger = $logger; // Removed

        if (empty($this->apiKey)) {
            Logger::warning("AIClient initialized without an API key for model {$this->model} at {$this->apiUrl}. API calls may fail.");
        }
        if (empty($this->apiUrl)) {
            Logger::error("AIClient initialized without an API URL. API calls will fail.");
            // throw new \InvalidArgumentException("AI API URL cannot be empty.");
        }
    }

    /**
     * 发送请求到AI API
     * @param string $prompt 用户提示（未格式化）
     * @param array $variables 用于替换提示中占位符的变量数组 e.g. ['keyword' => 'value']
     * @param string $systemPrompt 系统级提示 (可选)
     * @param float $temperature 控制生成文本的随机性 (可选)
     * @param int $maxTokens 最大生成token数 (可选)
     * @return string|null AI的响应文本，失败则返回null
     */
    public function sendRequest(
        string $promptTemplate,
        array $variables = [],
        string $systemPrompt = "",
        float $temperature = 0.7,
        ?int $maxTokens = 2048 // 增加默认值以应对更长的输出需求
    ): ?string {
        if (empty($this->apiUrl) || empty($this->apiKey)) {
            Logger::error("AIClient: API URL or API Key is not configured. Cannot send request.");
            return null;
        }

        $finalPrompt = $this->formatPrompt($promptTemplate, $variables);

        $messages = [];
        if (!empty($systemPrompt)) {
            $messages[] = ["role" => "system", "content" => $systemPrompt];
        }
        $messages[] = ["role" => "user", "content" => $finalPrompt];

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $temperature,
        ];

        if ($maxTokens !== null) {
            $payload['max_tokens'] = $maxTokens;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ]);
        // 增加超时设置
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 连接超时10秒
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);      // 总执行超时120秒 (AI可能需要较长时间响应)


        Logger::debug("AIClient: Sending request to {$this->apiUrl} for model {$this->model}", ['payload_model' => $this->model, 'prompt_length' => strlen($finalPrompt)]);
        self::logApiCall('request', $this->model, ['payload' => $payload]); // 记录请求

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        self::logApiCall('response', $this->model, ['http_code' => $httpCode, 'response_length' => strlen($response ?: '')], $response); // 记录响应

        if ($curlError) {
            Logger::error("AIClient: cURL Error for {$this->apiUrl}: " . $curlError);
            return null;
        }

        if ($httpCode >= 400) {
            Logger::error("AIClient: API request failed for {$this->apiUrl} with HTTP code {$httpCode}.", ['response_body' => $response]);
            return null;
        }

        $responseData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::error("AIClient: Failed to decode JSON response from {$this->apiUrl}.", ['response_body' => $response]);
            return null;
        }

        // 记录token使用情况 (如果API响应中包含)
        if (isset($responseData['usage'])) {
            self::logApiCall('usage', $this->model, $responseData['usage']);
        }


        if (isset($responseData['choices'][0]['message']['content'])) {
            Logger::info("AIClient: Successfully received response from model {$this->model}.");
            return $responseData['choices'][0]['message']['content'];
        } else {
            Logger::error("AIClient: Unexpected response structure from {$this->apiUrl}.", ['response_data' => $responseData]);
            return null;
        }
    }

    /**
     * 格式化提示词，替换占位符
     * @param string $template 提示词模板，如 "Translate {text} to {language}."
     * @param array $variables 变量数组，如 ['text' => 'Hello', 'language' => 'French']
     * @return string 格式化后的提示词
     */
    private function formatPrompt(string $template, array $variables): string {
        foreach ($variables as $key => $value) {
            $placeholder = '{' . $key . '}';
            // 对于数组或对象类型的变量，转换为JSON字符串，或者根据需要进行特定处理
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $template = str_replace($placeholder, (string)$value, $template);
        }
        return $template;
    }

    /**
     * 记录AI API调用日志的辅助方法
     *
     * @param string $type 'request', 'response', or 'usage'
     * @param string $model
     * @param array $data
     * @param string|null $rawResponse (optional) for 'response' type
     */
    private static function logApiCall(string $type, string $model, array $data, ?string $rawResponse = null): void {
        // 此方法依赖于一个更通用的日志记录机制，例如写入数据库的 `ai_api_logs` 表
        // 这里我们暂时只用 Logger 记录简要信息，或为将来扩展预留
        // 实际项目中，这里会调用 Database::execute() 来插入日志记录

        $logPayload = [
            'ai_type_event' => $type, // e.g., 'news_summarizing_request', 'news_summarizing_response'
            'model' => $model,
        ];

        if ($type === 'request') {
            // 注意：不要记录完整的 prompt 或 payload，除非确保已脱敏或仅用于内部调试
            // $logPayload['prompt_length'] = strlen($data['payload']['messages'][0]['content'] ?? '');
            Logger::debug("AI API Call: Request prepared", $logPayload);
        } elseif ($type === 'response') {
            $logPayload['http_code'] = $data['http_code'];
            // $logPayload['response_length'] = $data['response_length'];
            if ($data['http_code'] >= 400 && $rawResponse) {
                $logPayload['error_detail'] = substr($rawResponse, 0, 500); // 记录部分错误响应
            }
            Logger::debug("AI API Call: Response received", $logPayload);
        } elseif ($type === 'usage' && isset($data['prompt_tokens'])) {
            $logPayload['prompt_tokens'] = $data['prompt_tokens'];
            $logPayload['completion_tokens'] = $data['completion_tokens'] ?? 0;
            $logPayload['total_tokens'] = $data['total_tokens'] ?? ($logPayload['prompt_tokens'] + $logPayload['completion_tokens']);
            Logger::info("AI API Call: Token usage", $logPayload);
        }

        // 示例：未来写入数据库的调用
        // try {
        //     $dbLogData = [ /* map $logPayload to database columns */ ];
        //     Database::execute("INSERT INTO ai_api_logs (...) VALUES (...)", $dbLogData);
        // } catch (\Throwable $e) {
        //     Logger::error("Failed to log AI API call to database", ['error' => $e->getMessage()]);
        // }
    }
}
?>
