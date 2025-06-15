<?php

// 1. Include necessary files
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AIHelper.php';

// 2. Load Configuration
$apiKey = $config['api_key'] ?? null;
$apiEndpoint = $config['api_endpoint'] ?? null;
$userPrompts = $config['user_prompts'] ?? [];
$aiModel = $config['ai_model'] ?? null;

if (!$apiKey || !$apiEndpoint) {
    die("错误：API密钥或端点未配置。\n");
}
if (empty($userPrompts)) {
    die("错误：未配置用户提示。\n");
}
if (empty($aiModel)) {
    die("错误：AI 模型名称 (ai_model) 未在 config.php 中配置。\n");
}

// 3. Initialize AIHelper
$aiHelper = new AIHelper($apiKey, $apiEndpoint, $aiModel);

// 4. Fetch News for Each Prompt
$rawNewsDir = __DIR__ . '/../data/news_raw/';

// Ensure the raw news directory exists
if (!is_dir($rawNewsDir)) {
    if (!mkdir($rawNewsDir, 0777, true) && !is_dir($rawNewsDir)) {
        die(sprintf('错误：创建目录 "%s" 失败。%s', $rawNewsDir, "\n"));
    }
    echo "成功创建目录：" . $rawNewsDir . "\n";
}

foreach ($userPrompts as $index => $prompt) {
    echo "正在为提示获取新闻：\"{$prompt}\"\n";

    $messages = [
        ['role' => 'user', 'content' => $prompt]
    ];

    $newsContent = $aiHelper->sendPrompt($messages);

    if ($newsContent === false) {
        error_log("为提示 \"{$prompt}\" 获取新闻时出错：AIHelper 返回 false。");
        echo "为提示 \"{$prompt}\" 获取新闻失败。请检查错误日志。\n";
        continue;
    }

    if (empty(trim($newsContent))) {
        error_log("AI 未返回提示 \"{$prompt}\" 的内容。");
        echo "AI 未返回提示 \"{$prompt}\" 的内容。\n";
        continue;
    }

    // Generate a unique filename
    // Using timestamp and index for simplicity. A hash of the prompt could also be used.
    $filename = $rawNewsDir . time() . '_' . $index . '.txt';

    // Store the raw text response
    if (file_put_contents($filename, $newsContent)) {
        echo "已成功获取提示 \"{$prompt}\" 的新闻并将其存储在 {$filename}\n";
    } else {
        error_log("为提示 \"{$prompt}\" 保存新闻到文件 {$filename} 时出错。");
        echo "为提示 \"{$prompt}\" 保存新闻失败。请检查错误日志。\n";
    }
    // Add a small delay to avoid hitting rate limits if any, and to ensure unique filenames if prompts are processed very quickly
    sleep(1);
}

echo "新闻获取完成。\n";
