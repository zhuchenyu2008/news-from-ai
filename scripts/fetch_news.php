<?php

// 1. Include necessary files
$config = require __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/AIHelper.php';

// 2. Load Configuration
$apiKey = $config['api_key'] ?? null;
$apiEndpoint = $config['api_endpoint'] ?? null;
$userPrompts = $config['user_prompts'] ?? [];

if (!$apiKey || !$apiEndpoint) {
    die("Error: API key or endpoint not configured.\n");
}

if (empty($userPrompts)) {
    die("Error: No user prompts configured.\n");
}

// 3. Initialize AIHelper
$aiHelper = new AIHelper($apiKey, $apiEndpoint);

// 4. Fetch News for Each Prompt
$rawNewsDir = __DIR__ . '/../data/news_raw/';

// Ensure the raw news directory exists
if (!is_dir($rawNewsDir)) {
    if (!mkdir($rawNewsDir, 0777, true) && !is_dir($rawNewsDir)) {
        die(sprintf('Error: Failed to create directory "%s".%s', $rawNewsDir, "\n"));
    }
    echo "Created directory: " . $rawNewsDir . "\n";
}

foreach ($userPrompts as $index => $prompt) {
    echo "Fetching news for prompt: \"{$prompt}\"\n";

    $messages = [
        ['role' => 'user', 'content' => $prompt]
    ];

    $newsContent = $aiHelper->sendPrompt($messages);

    if ($newsContent === false) {
        error_log("Error fetching news for prompt \"{$prompt}\": AIHelper returned false.");
        echo "Failed to fetch news for prompt: \"{$prompt}\". Check error log.\n";
        continue;
    }

    // Generate a unique filename
    // Using timestamp and index for simplicity. A hash of the prompt could also be used.
    $filename = $rawNewsDir . time() . '_' . $index . '.txt';

    // Store the raw text response
    if (file_put_contents($filename, $newsContent)) {
        echo "Successfully fetched and stored news for prompt \"{$prompt}\" in {$filename}\n";
    } else {
        error_log("Error storing news for prompt \"{$prompt}\" in {$filename}.");
        echo "Failed to store news for prompt: \"{$prompt}\". Check error log.\n";
    }
    // Add a small delay to avoid hitting rate limits if any, and to ensure unique filenames if prompts are processed very quickly
    sleep(1);
}

echo "Finished fetching news.\n";
