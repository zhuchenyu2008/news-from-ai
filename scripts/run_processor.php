<?php

echo "Starting News Processor...\n";

// Define project root for easier path management
define('PROJECT_ROOT', dirname(__DIR__));

// 1. Include necessary files
$configPath = PROJECT_ROOT . '/config/config.php';
$aiHelperPath = PROJECT_ROOT . '/src/AIHelper.php';
$newsProcessorPath = PROJECT_ROOT . '/src/NewsProcessor.php';

if (!file_exists($configPath)) {
    die("Error: Configuration file not found at {$configPath}\n");
}
if (!file_exists($aiHelperPath)) {
    die("Error: AIHelper class file not found at {$aiHelperPath}\n");
}
if (!file_exists($newsProcessorPath)) {
    die("Error: NewsProcessor class file not found at {$newsProcessorPath}\n");
}

$config = require $configPath;
require_once $aiHelperPath;
require_once $newsProcessorPath;

// 2. Load Configuration
$apiKey = $config['api_key'] ?? null;
$apiEndpoint = $config['api_endpoint'] ?? null;

if (!$apiKey || !$apiEndpoint) {
    die("Error: API key or endpoint not configured in config.php.\n");
}

// Define data directories
$rawNewsDir = PROJECT_ROOT . '/data/news_raw/';
$htmlOutputDir = PROJECT_ROOT . '/data/news_html/';

// 3. Initialize AIHelper
try {
    $aiHelper = new AIHelper($apiKey, $apiEndpoint);
} catch (Exception $e) {
    die("Error initializing AIHelper: " . $e->getMessage() . "\n");
}

// 4. Initialize NewsProcessor
// The NewsProcessor constructor handles creation of htmlOutputDir if it doesn't exist.
try {
    $newsProcessor = new NewsProcessor($aiHelper, $rawNewsDir, $htmlOutputDir);
} catch (Exception $e) {
    die("Error initializing NewsProcessor: " . $e->getMessage() . "\n");
}

// 5. Call processNews()
echo "Running NewsProcessor...\n";
try {
    $newsProcessor->processNews();
} catch (Exception $e) {
    error_log("Exception during NewsProcessor->processNews(): " . $e->getMessage());
    echo "An error occurred during news processing. Check logs.\n";
}

echo "News Processor finished.\n";
