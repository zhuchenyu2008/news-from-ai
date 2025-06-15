<?php

echo "开始新闻处理器...\n"; // Translated

// Define project root for easier path management
define('PROJECT_ROOT', dirname(__DIR__));

// 1. Include necessary files
$configPath = PROJECT_ROOT . '/config/config.php';
$aiHelperPath = PROJECT_ROOT . '/src/AIHelper.php';
$newsProcessorPath = PROJECT_ROOT . '/src/NewsProcessor.php';

if (!file_exists($configPath)) {
    die("错误：配置文件未找到于 {$configPath}\n");
}
if (!file_exists($aiHelperPath)) {
    die("错误：AIHelper 类文件未找到于 {$aiHelperPath}\n");
}
if (!file_exists($newsProcessorPath)) {
    die("错误：NewsProcessor 类文件未找到于 {$newsProcessorPath}\n");
}

$config = require $configPath;
require_once $aiHelperPath;
require_once $newsProcessorPath;

// 2. Load Configuration
$apiKey = $config['api_key'] ?? null;
$apiEndpoint = $config['api_endpoint'] ?? null;
$htmlPromptTemplate = $config['html_generation_prompt_template'] ?? null;
$preferredHtmlStyle = $config['preferred_html_style'] ?? null;
$aiModel = $config['ai_model'] ?? null;

if (!$apiKey || !$apiEndpoint) {
    die("错误：API密钥或端点未在 config.php 中配置。\n");
}
if (empty($htmlPromptTemplate)) {
    die("错误：HTML 生成提示模板 (html_generation_prompt_template) 未在 config.php 中配置。\n");
}
if (empty($preferredHtmlStyle)) { // Also check preferred_html_style, though 'auto' is a valid empty-ish value, explicit check for not null.
    die("错误：首选 HTML 样式 (preferred_html_style) 未在 config.php 中配置。\n");
}
if (empty($aiModel)) {
    die("错误：AI 模型名称 (ai_model) 未在 config.php 中配置。\n");
}

// Define data directories
$rawNewsDir = PROJECT_ROOT . '/data/news_raw/';
$htmlOutputDir = PROJECT_ROOT . '/data/news_html/';

// 3. Initialize AIHelper
try {
    $aiHelper = new AIHelper($apiKey, $apiEndpoint, $aiModel);
} catch (Exception $e) {
    die("错误：初始化 AIHelper 失败：" . $e->getMessage() . "\n");
}

// 4. Initialize NewsProcessor
// The NewsProcessor constructor handles creation of htmlOutputDir if it doesn't exist.
try {
    $newsProcessor = new NewsProcessor(
        $aiHelper,
        $rawNewsDir,
        $htmlOutputDir,
        $htmlPromptTemplate,
        $preferredHtmlStyle
    );
} catch (Exception $e) {
    die("错误：初始化 NewsProcessor 失败：" . $e->getMessage() . "\n");
}

// 5. Call processNews()
echo "运行 NewsProcessor...\n"; // Translated
try {
    $newsProcessor->processNews();
} catch (Exception $e) {
    error_log("NewsProcessor->processNews() 执行期间发生异常：" . $e->getMessage()); // Translated
    echo "新闻处理过程中发生错误。请检查日志。\n"; // Translated
}

echo "新闻处理器已完成。\n"; // Translated
