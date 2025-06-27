<?php
// Placeholder for config.php
// This file should be created by copying config.php.example and filling in actual values.
// It's .gitignore'd by convention.

// IMPORTANT: Ensure this file is loaded before any other project files that need these constants.

// Example of how you might load user config if it exists
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} elseif (file_exists(__DIR__ . '/config.php.example')) {
    // Fallback or error, depending on desired behavior if config.php is missing
    // For now, we'll define placeholders if no specific config is found.
    // In a real scenario, you'd likely copy config.php.example to config.php and fill it.

    // Default/fallback Database configuration (SHOULD BE OVERRIDDEN IN A REAL config.php)
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'news_from_ai_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');

    // Default/fallback User custom prompt
    define('USER_NEWS_PROMPT', 'Latest advancements in embodied AI and open-source robotics projects');

    // Default/fallback Cron schedule
    define('CRON_SCHEDULE', '0 */2 * * *');

    // Default/fallback RSS feeds
    define('RSS_FEEDS', [
        // 'https://www.wired.com/feed/category/science/latest/rss',
        // 'https://techcrunch.com/category/artificial-intelligence/feed/',
        // 'http://feeds.bbci.co.uk/news/technology/rss.xml'
    ]);

    // Default/fallback Search API config
    define('SEARCH_API_CONFIG', [
        'api_key' => 'your_search_api_key_here',
        'api_url' => 'https://api.searchprovider.com/search' // Example, replace with actual
    ]);

    // Default/fallback AI configurations
    define('AI_CONFIGS', [
        'query_generator' => [
            'api_key' => 'your_openai_compatible_api_key_here',
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'model'   => 'gpt-4-turbo'
        ],
        'news_analyzer' => [
            'api_key' => 'your_openai_compatible_api_key_here',
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'model'   => 'gpt-4-turbo'
        ],
        'rss_summarizer' => [
            'api_key' => 'your_openai_compatible_api_key_here',
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'model'   => 'gpt-3.5-turbo'
        ]
    ]);
}

// Ensure vendor/autoload.php is loaded.
// This path assumes config.php is in the project root.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
} else {
    // Handle case where autoload is not found, e.g., if Composer install hasn't run
    // For now, we'll just note it. In a real app, you might die() or throw an exception.
    // error_log("Error: vendor/autoload.php not found. Please run 'composer install'.");
}

?>
