<?php
/**
 * 调用OpenAI通用接口
 */
require_once __DIR__ . '/logger.php';

function call_openai(array $config, string $prompt): ?string {
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['api_key']
    ];

    $postData = json_encode([
        'model' => $config['model'],
        'messages' => [
            ['role' => 'user', 'content' => $prompt]
        ]
    ]);

    $ch = curl_init($config['base_url'] . '/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $response = curl_exec($ch);
    if ($response === false) {
        log_message('调用OpenAI失败: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? null;
}
