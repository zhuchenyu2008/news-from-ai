<?php
// news-from-ai - AI服务类

require_once dirname(__DIR__) . '/includes/functions.php';

class AIService {
    private array $config;
    private array $aiConfigs;

    public function __construct() {
        $this->config = load_config();
        $this->aiConfigs = $this->config['ai'];
    }

    /**
     * 与OpenAI兼容的API进行通用交互
     *
     * @param string $prompt 提示内容
     * @param string $aiConfigKey 使用的AI配置键名 (来自config.php中的 'ai' 部分)
     * @param array $options 额外的API参数 (如 temperature, max_tokens)
     * @param string|null $systemMessage 可选的系统消息
     * @return string|null AI的响应内容 (通常是choices[0]->message->content) 或在失败时返回null
     */
    public function get_ai_response(string $prompt, string $aiConfigKey = 'default', array $options = [], ?string $systemMessage = null): ?string {
        if (!isset($this->aiConfigs[$aiConfigKey])) {
            log_message('error', "AI配置键 '{$aiConfigKey}' 未在config.php中找到。");
            return null;
        }

        $currentAIConfig = $this->aiConfigs[$aiConfigKey];
        $apiKey = $currentAIConfig['api_key'];
        $apiUrl = $currentAIConfig['api_url'];
        $model = $options['model'] ?? $currentAIConfig['model']; // 允许通过options覆盖模型

        if (empty($apiKey) || $apiKey === 'YOUR_OPENAI_API_KEY' || str_starts_with($apiKey, 'YOUR_OPENAI_API_KEY_FOR_')) {
            log_message('error', "AI API Key for '{$aiConfigKey}' 未配置或使用的是占位符。");
            return null;
        }

        $messages = [];
        if ($systemMessage !== null) {
            $messages[] = ["role" => "system", "content" => $systemMessage];
        }
        $messages[] = ["role" => "user", "content" => $prompt];

        $postData = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7, // 默认temperature
            'max_tokens' => $options['max_tokens'] ?? 1500,  // 默认max_tokens
        ];

        // 合并其他可能的OpenAI参数
        if (isset($options['top_p'])) $postData['top_p'] = $options['top_p'];
        if (isset($options['n'])) $postData['n'] = $options['n'];
        if (isset($options['stream'])) $postData['stream'] = $options['stream'];
        // ... 可以根据需要添加更多参数

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 增加超时时间至120秒
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // 生产环境建议开启

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $endTime = microtime(true);
        $executionTimeMs = round(($endTime - $startTime) * 1000);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        log_message('debug', "AI API请求详情", [
            'url' => $apiUrl,
            'config_key' => $aiConfigKey,
            'model' => $model,
            'prompt_length' => mb_strlen($prompt),
            'http_code' => $httpCode,
            'execution_time_ms' => $executionTimeMs
        ]);

        if ($curlError) {
            log_message('error', "AI API cURL错误 for {$aiConfigKey}: " . $curlError, ['url' => $apiUrl]);
            // 记录到ai_tasks_log (如果适用，但这里是通用函数，具体记录逻辑可在调用处实现)
            return null;
        }

        if ($httpCode >= 400) {
            log_message('error', "AI API HTTP错误 for {$aiConfigKey}: " . $httpCode, ['url' => $apiUrl, 'response' => $response]);
            // 记录到ai_tasks_log
            return null;
        }

        $responseData = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message('error', "AI API JSON解码失败 for {$aiConfigKey}: " . json_last_error_msg(), ['response_preview' => substr($response, 0, 200)]);
            // 记录到ai_tasks_log
            return null;
        }

        if (isset($responseData['error'])) {
            log_message('error', "AI API返回错误 for {$aiConfigKey}: " . $responseData['error']['message'], ['error_details' => $responseData['error']]);
            // 记录到ai_tasks_log
            return null;
        }

        if (isset($responseData['choices'][0]['message']['content'])) {
            $content = $responseData['choices'][0]['message']['content'];
            log_message('info', "AI API成功获取响应 for {$aiConfigKey}", ['response_length' => mb_strlen($content)]);
            // 记录到ai_tasks_log (成功)
            return $content;
        } else {
            log_message('warning', "AI API响应中未找到预期的内容结构 for {$aiConfigKey}", ['response_data' => $responseData]);
            // 记录到ai_tasks_log (异常响应)
            return null;
        }
    }
}
?>
